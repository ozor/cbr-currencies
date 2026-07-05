<?php

namespace App\Command;

use App\Messenger\Message\WarmupRatesMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Отправляет WarmupRatesMessage в async transport для прогрева дневных snapshot'ов курсов ЦБ РФ.
 *
 * Примеры использования:
 *
 * - Прогрев за последние 180 дней (по умолчанию):
 *   `php bin/console app:cbr:warmup-rates`
 *
 * - Начать с конкретной даты:
 *   `php bin/console app:cbr:warmup-rates 2024-01-10`
 *
 * - Указать количество дней:
 *   `php bin/console app:cbr:warmup-rates 2024-01-10 --days=30`
 *   или кратко:
 *   `php bin/console app:cbr:warmup-rates 2024-01-10 -d 30`
 *
 * Замечания:
 * - Команда пропускает выходные (суббота/воскресенье).
 * - Для обработки отправленных сообщений запустите worker:
 *   `php bin/console messenger:consume async -vv`
 */
#[AsCommand(
    name: 'app:cbr:warmup-rates',
    description: 'Отправляет задачи прогрева snapshot\'ов курсов ЦБ РФ в async transport',
)]
class WarmupRatesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('start-date', InputArgument::OPTIONAL, 'Начальная дата (YYYY-MM-DD)', 'now')
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Количество дней для обработки (назад от start-date)', 180)
        ;
    }

    /**
     * @throws ExceptionInterface
     * @throws \DateMalformedStringException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $startDate = new \DateTimeImmutable($input->getArgument('start-date'));
        } catch (\Exception $e) {
            $io->error('Неверный формат даты: '.$e->getMessage());

            return Command::FAILURE;
        }

        $days = (int) $input->getOption('days');

        $io->title('Прогрев snapshot\'ов курсов ЦБ РФ');
        $io->info(sprintf('Обработка %d дней с %s', $days, $startDate->format('Y-m-d')));

        $messagesSent = 0;

        for ($i = 0; $i < $days; ++$i) {
            $date = $startDate->modify("-$i days");

            // Пропускаем выходные (суббота и воскресенье)
            if (in_array($date->format('N'), ['6', '7'])) {
                $io->note(sprintf('Пропуск %s (выходной день)', $date->format('Y-m-d')));
                continue;
            }

            $this->messageBus->dispatch(new WarmupRatesMessage($date));
            ++$messagesSent;

            $io->writeln(sprintf('Задача отправлена для даты: %s', $date->format('Y-m-d')));
        }

        $io->success(sprintf('Отправлено %d задач прогрева в async transport', $messagesSent));
        $io->note('Запустите worker для обработки: php bin/console messenger:consume async -vv');

        return Command::SUCCESS;
    }
}

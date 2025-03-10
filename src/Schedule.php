<?php

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        #[Target('schedule_cache_pool')]
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            // purge old (non-schedule) messenger monitor history daily
            ->add(RecurringMessage::cron(
                '#midnight',
                new RunCommandMessage('messenger:monitor:purge --exclude-schedules'),
            )->withJitter())

            // purge old schedule messenger monitor history (keeping last 20)
            ->add(RecurringMessage::cron(
                '#midnight',
                new RunCommandMessage('messenger:monitor:schedule:purge --remove-orphans --keep 20'),
            )->withJitter())

            // add your own tasks here
        ;
    }
}

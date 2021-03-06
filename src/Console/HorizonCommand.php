<?php

namespace Laravel\Horizon\Console;

use Illuminate\Console\Command;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\ProvisioningPlan;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class HorizonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horizon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a master supervisor in the foreground';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $repository = app(MasterSupervisorRepository::class);

        if ($repository->find(MasterSupervisor::name())) {
            return $this->comment('A master supervisor is already running on this machine.');
        }

        $master = (new MasterSupervisor)->handleOutputUsing(function ($type, $line) {
            $this->output->write($line);
        });

        ProvisioningPlan::get(MasterSupervisor::name())->deploy(
            config('horizon.env') ?? config('app.env')
        );

        $this->info('Horizon started successfully.');

        pcntl_async_signals(true);

        pcntl_signal(SIGINT, function () use ($master) {
            $this->line('Shutting down...');

            return $master->terminate();
        });

        $master->monitor();
    }
}

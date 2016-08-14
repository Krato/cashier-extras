<?php

namespace Travoltron\CashierExtras\Commands;

use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Console\Command;
use Stripe\Plan as StripePlan;
use Stripe\Error\InvalidRequest as StripeErrorInvalidRequest;

class CreatePlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:make-plan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new plan to use with your Stripe account';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Creating a new plan at Stripe');
        // Check that the keys are set
        $validKeys = Artisan::call('stripe:check-keys');
        $envs = collect($validKeys)->filter(function($val, $key) {
            return $val === true;
        });
        $env = $this->choice('Which Stripe environment to use?', $envs);

        // Test keys are set and appear to be correct
        Stripe::setApiKey(($env == 0)?env('STRIPE_TEST_SECRET'):env('STRIPE_SECRET'));
        $name = ($this->ask('What is the name of this plan?'));
        $data['name'] = $name;
        $data['id'] = str_slug($name);
        $amount = $this->ask('How much does this plan cost?');
        $data['amount'] = (stristr($amount, '.'))?$amount * 100:$amount;
        $data['currency'] = $this->ask('Currency code:', 'usd');
        $data['interval'] = $this->choice('How frequently does this plan bill?', ['day', 'week', 'month', 'year']);
        $data['interval_count'] = $this->ask('How many intervals are between billing cycles? (eg: 15 days, 3 months)', 1);
        $data['statement_descriptor'] = $this->ask('How should this show up on a billing statement? (22 character max)');
        $data['trial_period_days'] = $this->ask('If there is a trial period, how many days?', 0);
        $this->comment('Plan details:');
        $this->comment('Name: '.$data['name']);
        $this->comment('ID: '.$data['id']);
        $this->comment('Amount: '.$data['amount'].' ('.strtoupper($data['currency']).')');
        $this->comment('Bills every '.$data['interval_count'].' '.str_plural($data['interval'], $data['interval_count']).'.');
        $this->comment(($data['trial_period_days'] == 0)?'There is no trial period for this plan.':'Trial period lasts for '.$data['trial_period_days'].' days.');
        $this->comment('Appears on statement as: '.$data['statement_descriptor']);

        if ($this->confirm('Does this look right to you? [y|N]')) {
            StripePlan::create($data);
        }
        $this->info('Successfully created plans and coupons for testing.');
        return;
    }
}
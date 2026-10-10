<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {email : The email address to send the test to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to verify email configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $this->info('Email Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Mailer', config('mail.default')],
                ['From Address', config('mail.from.address')],
                ['From Name', config('mail.from.name')],
            ]
        );

        $this->newLine();
        $this->info("Sending test email to: {$email}");

        try {
            Mail::raw(
                "This is a test email from FlipStack.\n\nIf you received this email, your mail configuration is working correctly.\n\nSent at: ".now()->toDateTimeString(),
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('FlipStack Email Test');
                }
            );

            $this->newLine();
            $this->info('Test email sent successfully!');

            if (config('mail.default') === 'log') {
                $this->newLine();
                $this->warn('Note: Mail driver is set to "log". Email was written to the log file instead of being sent.');
                $this->line('Check storage/logs/laravel.log to see the email content.');
                $this->line('To send real emails, set MAIL_MAILER=resend and configure RESEND_KEY in your .env file.');
            }

            return 0;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('Failed to send test email!');
            $this->error('Error: '.$e->getMessage());

            return 1;
        }
    }
}

<?php

namespace App\Providers;

use App\Events\EmailFailed;
use App\Events\EmailSent;
use App\Events\ReplyReceived;
use App\Listeners\HandleReplyReceived;
use App\Listeners\LogEmailFailedToTimeline;
use App\Listeners\LogEmailSentToTimeline;
use App\Models\Contact;
use App\Models\Document;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailSignature;
use App\Models\EmailTemplate;
use App\Models\Opportunity;
use App\Policies\ContactPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EmailAccountPolicy;
use App\Policies\EmailMessagePolicy;
use App\Policies\EmailSignaturePolicy;
use App\Policies\EmailTemplatePolicy;
use App\Policies\OpportunityPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends AuthServiceProvider
{
    /**
     * Model → Policy map registered with Laravel's Gate.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        EmailAccount::class  => EmailAccountPolicy::class,
        Contact::class       => ContactPolicy::class,
        Opportunity::class   => OpportunityPolicy::class,
        Document::class      => DocumentPolicy::class,
        EmailTemplate::class => EmailTemplatePolicy::class,
        EmailMessage::class  => EmailMessagePolicy::class,
        EmailSignature::class => EmailSignaturePolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // ---------------------------------------------------------------
        // Global route parameter constraints
        // Controllers type-hint `{id}` route params as `int`, so a
        // non-numeric segment (e.g. /emails/compose) would otherwise throw
        // a TypeError (500) instead of resolving to a 404.
        // ---------------------------------------------------------------
        Route::pattern('id', '[0-9]+');

        // ---------------------------------------------------------------
        // Event → Listener registrations
        // ---------------------------------------------------------------
        Event::listen(EmailSent::class,     LogEmailSentToTimeline::class);
        Event::listen(EmailFailed::class,   LogEmailFailedToTimeline::class);
        Event::listen(ReplyReceived::class, HandleReplyReceived::class);

        // Paddle webhook events → tenant plan/status sync
        Event::subscribe(\App\Listeners\SyncTenantPlanFromPaddle::class);
    }
}

<?php

use App\Jobs\DispatchPendingOutboxMessages;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchPendingOutboxMessages)->everyMinute()->withoutOverlapping();

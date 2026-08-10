<?php

namespace App\Jobs;

use App\Http\Services\InvoiceService;
use App\Models\Property;
use App\Models\User;
use App\Notifications\InvoicesGeneratedNotification;
use App\Notifications\JobFailedNotification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Log;

class GenerateInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Define Anonymous Class
        $result = new class
        {
            public $users;

            public $properties;

            public $units;

            public $invoices;

            public $message;

            public $isForAdmin;

            public function __construct()
            {
                $this->users = collect([]);
                $this->properties = collect([]);
                $this->units = collect([]);
                $this->invoices = collect([]);
                $this->message = "Invoice Processing Completed Successfully.";
                $this->isForAdmin = true;
            }
        };

        $properties = Property::where("invoice_date", now()->day)
            // Check that user of property has active subscription
            ->whereHas("user", function ($query) {
                $query->whereHas("userSubscriptionPlans", function ($query) {
                    $query->where("status", "active")
                        ->where("end_date", ">", now());
                });
            })
            ->get();

        $properties->each(function ($property) use (&$result) {
            $units = $property
                ->units()
                // Ensure Unit has a tenant
                ->whereHas("userUnits", function ($query) {
                    $query->whereNull("vacated_at");
                })
                ->get();

            // $result->units->push($units);

            $units->each(function ($unit) use (&$result) {
                // Loop through each invoice type
                collect([
                    "rent",
                    "service",
                    "water",
                    "electricity",
                    "garbage",
                    "security",
                    "internet",
                    "cleaning",
                    "parking",
                ])
                    ->each(function ($type) use ($unit, &$result) {
                        // Prevent Rent Invoice from being generated if Deposit invoice exists for that month
                        if ($type === "rent") {
                            $depositInvoice = $unit->currentUserUnit()
                                ?->invoices()
                                ->where("type", "deposit")
                                ->where("month", now()->month)
                                ->where("year", now()->year)
                                ->first();

                            if ($depositInvoice) {
                                return;
                            }
                        }

                        switch ($type) {
                            case "service":
                                if ($unit->service_charge?->service < 1) {
                                    return;
                                }
                                break;

                            case "electricity":
                                if ($unit->service_charge?->electricity < 1) {
                                    return;
                                }
                                break;

                            case "garbage":
                                if ($unit->service_charge?->garbage < 1) {
                                    return;
                                }
                                break;

                            case "security":
                                if ($unit->service_charge?->security < 1) {
                                    return;
                                }
                                break;

                            case "internet":
                                if ($unit->service_charge?->internet < 1) {
                                    return;
                                }
                                break;

                            case "cleaning":
                                if ($unit->service_charge?->cleaning < 1) {
                                    return;
                                }
                                break;

                            case "parking":
                                if ($unit->service_charge?->parking < 1) {
                                    return;
                                }
                                break;

                            default:
                                // code...
                                break;
                        }

                        // Check that water reading exists for this month
                        if ($type === "water") {
                            $waterReading = $unit->currentUserUnit()
                                ?->waterReadings()
                                ->where("month", now()->month)
                                ->where("year", now()->year)
                                ->first();

                            if (! $waterReading) {
                                return;
                            }
                        }

                        // Check if Invoice Exists for type, month and year
                        $invoice = $unit->currentUserUnit()
                            ?->invoices()
                            ->where("type", $type)
                            ->where("month", now()->month)
                            ->where("year", now()->year)
                            ->first();

                        if ($invoice) {
                            Log::info("Invoice for Unit {$unit->id}, Type: {$type} already exists.");

                            return;
                        }

                        $superAdmin = User::firstOrCreate(
                            ["email" => "al@property.black.co.ke"],
                            [
                                'name' => 'Super Admin',
                                'email' => 'al@property.black.co.ke',
                                'email_verified_at' => now(),
                                'avatar' => 'avatars/male-avatar.png',
                                // 'phone' => '0700364446',
                                'password' => Hash::make('al@property.black.co.ke'),
                                // 'remember_token' => Str::random(10),
                                'gender' => 'male',
                            ]
                        );

                        // Make Request
                        $request = new Request([
                            "userUnitIds" => [$unit->currentUserUnit()?->id],
                            "type" => $type,
                            "month" => now()->month,
                            "year" => now()->year,
                            "createdBy" => $superAdmin->id,
                        ]);

                        try {
                            [$saved, $message, $invoice] = (new InvoiceService)->store($request);

                            $result->users->push($invoice->userUnit->unit->property->user);
                            $result->properties->push($invoice->userUnit->unit->property);
                            $result->units->push($invoice->userUnit->unit);
                            $result->invoices->push($invoice);

                            [$saved, $message, $invoice] = (new InvoiceService)->sendEmail($invoice->id);
                            // [$saved, $message, $invoice] = (new InvoiceService)->sendSMS($invoice->id);
                        } catch (Exception $e) {
                            Log::error("Invoice {$type} Error, Unit {$unit->id}: ".$e->getMessage());
                            $result->message = "Invoice Processing Encountered Errors.";
                        }
                    });
            });
        });

        $result->users = $result->users->unique()->values();
        $result->properties = $result->properties->unique()->values();
        $result->units = $result->units->unique()->values();
        $result->invoices = $result->invoices->unique()->values();

        // Send notifications to each user with their specific data
        foreach ($result->users as $user) {
            // Create a fresh copy for each user
            $userResult = new class
            {
                public $users;

                public $properties;

                public $units;

                public $invoices;

                public $message;

                public $isForAdmin;

                public function __construct()
                {
                    $this->users = collect([]);
                    $this->properties = collect([]);
                    $this->units = collect([]);
                    $this->invoices = collect([]);
                    $this->message = "Invoice Processing Completed Successfully.";
                    $this->isForAdmin = false;
                }
            };

            // Set the current user
            $userResult->message = $result->message;

            // Get Users Properties
            $userResult->properties = $result
                ->properties
                ->filter(fn ($property) => $property->user_id == $user->id)
                ->values();

            // Get Users Units
            $propertyIds = $userResult
                ->properties
                ->map(fn ($property) => $property->id)
                ->unique();

            $userResult->units = $result
                ->units
                ->filter(function ($unit) use ($propertyIds) {
                    return $propertyIds->contains($unit->property_id);
                })
                ->values();

            $unitIds = $userResult
                ->units
                ->map(fn ($unit) => $unit->id)
                ->unique();

            $userResult->invoices = $result
                ->invoices
                ->filter(function ($invoice) use ($unitIds) {
                    return $unitIds->contains($invoice->userUnit->unit->id);
                })
                ->values();

            if ($user->settings?->invoicesGeneratedNotification) {
                $user->notify(new InvoicesGeneratedNotification($userResult));
            }
        }

        // Send Notification to Property Owners
        $superAdmin = User::firstOrCreate(
            ["email" => "al@property.black.co.ke"],
            [
                'name' => 'Super Admin',
                'email' => 'al@property.black.co.ke',
                'email_verified_at' => now(),
                'avatar' => 'avatars/male-avatar.png',
                // 'phone' => '0700364446',
                'password' => Hash::make('al@property.black.co.ke'),
                // 'remember_token' => Str::random(10),
                'gender' => 'male',
            ]
        );

        if ($superAdmin->settings?->superInvoicesGeneratedNotification) {
            $superAdmin->notify(new InvoicesGeneratedNotification($result));
        }

        return $result;
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("GenerateInvoicesJob failed", [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'time' => now(),
        ]);

        // Send detailed failure notification
        try {
            // Use first or create instead of where to avoid issues if user is not found
            $superAdmin = User::firstOrCreate(
                ['email' => 'alphaxardgacuuru47@gmail.com']
            );

            $additionalData = [
                'server' => request()->server('SERVER_NAME') ?? 'Unknown',
                'environment' => app()->environment(),
                'memory_usage' => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - LARAVEL_START,
            ];

            $superAdmin->notify(new JobFailedNotification(
                'GenerateInvoicesJob',
                $exception,
                $additionalData
            ));
        } catch (Exception $e) {
            Log::error(
                "Failed to send detailed job failure notification: ".$e->getMessage()
            );
        }
    }
}

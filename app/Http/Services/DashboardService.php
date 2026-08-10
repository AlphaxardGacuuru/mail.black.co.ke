<?php

namespace App\Http\Services;

use App\Http\Resources\UnitResource;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UserUnit;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService extends Service
{
    public $allMonths = [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
    ];

    public function index(array $propertyIds)
    {
        return [
            "units" => $this->units($propertyIds),
            "rent" => $this->rent($propertyIds),
            "water" => $this->water($propertyIds),
            "serviceCharge" => $this->serviceCharge($propertyIds),
        ];
    }

    public function narration(array $metrics)
    {
        $prompt = <<<TEXT
        Property portfolio dashboard metrics as of today:

        Occupancy
        - Occupied units: {$metrics['units']['totalOccupied']}
        - Unoccupied units: {$metrics['units']['totalUnoccupied']}
        - Occupancy rate: {$metrics['units']['percentage']}%

        Rent collections
        - Total collected: KES {$metrics['rent']['paid']}
        - Outstanding balance: KES {$metrics['rent']['due']}
        - Collection rate: {$metrics['rent']['percentage']}%

        Water billing
        - Total collected: KES {$metrics['water']['paid']}
        - Outstanding balance: KES {$metrics['water']['due']}
        - Collection rate: {$metrics['water']['percentage']}%
        - Usage last month: {$metrics['water']['usageLastMonth']} units
        - Usage two months ago: {$metrics['water']['usageTwoMonthsAgo']} units

        Service charge
        - Total collected: KES {$metrics['serviceCharge']['paid']}
        - Outstanding balance: KES {$metrics['serviceCharge']['due']}
        - Collection rate: {$metrics['serviceCharge']['percentage']}%

        Provide a narrative insight and highlights for the property manager.
        TEXT;

        return $prompt;
    }

    public function crm()
    {
        $leads = Lead::all();

        return [
            "leads" => [
                "total" => $leads->count(),
                "individual" => $leads->where("type", "individual")->count(),
                "business" => $leads->where("type", "business")->count(),
                "thisMonth" => $leads->where("created_at", ">=", Carbon::now()->startOfMonth())->count(),
                "thisYear" => $this->leadsThisYear(),
            ],
            "mapData" => $leads->map(function ($lead) {
                return [
                    "id" => $lead->id,
                    "name" => $lead->name,
                    "lat" => (float) $lead->latitude,
                    "lng" => (float) $lead->longitude,
                    "address" => $lead->address,
                ];
            }),
        ];
    }

    public function leadsThisYear()
    {
        $getLeadsThisYear = Lead::select(DB::raw("MONTH(created_at) as month"), DB::raw("count(*) as count"))
            ->whereYear("created_at", Carbon::now()->year)
            ->groupBy("month")
            ->get()
            ->map(fn($item) => [
                "month" => $this->allMonths[$item->month - 1],
                "count" => $item->count,
            ]);

        [$labels, $data] = $this->getLabelsAndData($getLeadsThisYear);

        return [
            "labels" => $labels,
            "data" => $data,
        ];
    }

    /*
     * Properties
     */

    public function properties($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $propertyQuery = new Property;
        } else {
            $propertyQuery = Property::whereIn("id", $propertyIds);
        }

        $total = $propertyQuery->count();

        $ids = [];
        $names = [];
        $units = [];

        $propertyQuery
            ->get()
            ->each(function ($property) use (&$ids, &$names, &$units) {
                $ids[] = $property->id;
                $names[] = $property->name;
                $units[] = $property
                    ->units
                    ->count();
            });

        return [
            "total" => $total,
            "ids" => $ids,
            "names" => $names,
            "units" => $units,
        ];
    }

    /*
     * Units
     */

    public function units($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $unitsQuery = new Unit;
        } else {
            $unitsQuery = Unit::whereIn("property_id", $propertyIds);
        }

        $total = $unitsQuery->count();

        $totalOccupied = $unitsQuery
            ->whereHas("userUnits", fn($query) => $query
                ->whereNotNull("occupied_at")
                ->whereNull("vacated_at"))
            ->count();

        $totalUnoccupied = $unitsQuery
            ->whereHas("userUnits", fn($query) => $query
                ->whereNull("occupied_at")
                ->whereNull("vacated_at")
                ->orWhereNotNull("occupied_at")
                ->whereNotNull("vacated_at"))
            ->count();

        $totalUnoccupied = $total - $totalOccupied;

        $units = Unit::whereIn("property_id", $propertyIds)
            ->orderBy("id", "DESC")
            ->paginate(20);

        $units = UnitResource::collection($units);

        return [
            "totalOccupied" => $totalOccupied,
            "totalUnoccupied" => $totalUnoccupied,
            "percentage" => $this->percentage($totalOccupied, $totalUnoccupied),
            "list" => $units,
            "tenantsThisYear" => $this->tenantsThisYear($propertyIds),
            "vacanciesThisYear" => $this->vacanciesThisYear($propertyIds),
        ];
    }

    public function tenantsThisYear($propertyIds)
    {
        // Get tenant count for each month of the current year up to current month
        $currentMonth = Carbon::now()->month;

        $getTenantsThisYear = collect(range(1, $currentMonth))->map(function ($monthNumber) use ($propertyIds) {
            $startOfMonth = Carbon::create(Carbon::now()->year, $monthNumber, 1)->startOfDay();
            $endOfMonth = Carbon::create(Carbon::now()->year, $monthNumber, 1)->endOfMonth()->endOfDay();

            $isSuper = in_array("All", $propertyIds);

            if ($isSuper) {
                $userUnitQuery = new UserUnit;
            } else {
                $userUnitQuery = UserUnit::whereHas("unit.property", function ($query) use ($propertyIds) {
                    $query->whereIn("id", $propertyIds);
                });
            }

            // Count tenants who were occupied during this month
            $count = $userUnitQuery
                // ->whereNotNull("occupied_at")
                ->where("occupied_at", "<=", $endOfMonth)
                ->where(function ($query) use ($startOfMonth) {
                    $query->whereNull("vacated_at")
                        ->orWhere("vacated_at", ">=", $startOfMonth);
                })
                ->count();

            return [
                "month" => $this->allMonths[$monthNumber - 1],
                "count" => $count,
            ];
        });

        [$labels, $data] = $this->getLabelsAndData($getTenantsThisYear);

        return [
            "labels" => $labels,
            "data" => $data,
        ];
    }

    public function vacanciesThisYear($propertyIds)
    {
        // Get vacancy count for each month of the current year up to current month
        $currentMonth = Carbon::now()->month;
        $totalUnits = Unit::whereIn("property_id", $propertyIds)->count();

        $getVacanciesThisYear = collect(range(1, $currentMonth))->map(function ($monthNumber) use ($propertyIds, $totalUnits) {
            $startOfMonth = Carbon::create(Carbon::now()->year, $monthNumber, 1)->startOfDay();
            $endOfMonth = Carbon::create(Carbon::now()->year, $monthNumber, 1)->endOfMonth()->endOfDay();

            $isSuper = in_array("All", $propertyIds);

            if ($isSuper) {
                $userUnitQuery = new UserUnit;
            } else {
                $userUnitQuery = UserUnit::whereHas("unit.property", function ($query) use ($propertyIds) {
                    $query->whereIn("id", $propertyIds);
                });
            }

            // Count tenants who were occupied during this month
            $occupiedCount = $userUnitQuery
                // ->whereNotNull("occupied_at")
                ->where("occupied_at", "<=", $endOfMonth)
                ->where(function ($query) use ($startOfMonth) {
                    $query->whereNull("vacated_at")
                        ->orWhere("vacated_at", ">=", $startOfMonth);
                })
                ->count();

            // Calculate vacancies (total units - occupied units)
            $vacancyCount = $totalUnits - $occupiedCount;

            return [
                "month" => $this->allMonths[$monthNumber - 1],
                "count" => $vacancyCount,
            ];
        });

        [$labels, $data] = $this->getLabelsAndData($getVacanciesThisYear);

        return [
            "labels" => $labels,
            "data" => $data,
        ];
    }

    /*
     * Rent
     */

    public function rent($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $rentQuery = Invoice::where("type", "rent");
        } else {
            $rentQuery = Invoice::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            })->where("type", "rent");
        }

        $paid = $rentQuery
            ->sum("paid");

        $due = $rentQuery
            ->sum("balance");

        return [
            "paid" => $paid,
            "due" => $due,
            "total" => number_format($paid + $due),
            "percentage" => $this->percentage($paid, $due),
            "paidThisYear" => $this->rentPaidThisYear($propertyIds),
            "unpaidThisYear" => $this->rentDueThisYear($propertyIds),
        ];
    }

    public function rentPaidThisYear($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $rentQuery = Invoice::where("type", "rent");
        } else {
            $rentQuery = Invoice::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            })->where("type", "rent");
        }

        $getRentThisYear = $rentQuery
            ->select("invoices.month", DB::raw("sum(paid) as count"))
            ->where("year", Carbon::now()->year)
            ->groupBy("month")
            ->get()
            ->map(fn($item) => [
                "month" => $this->allMonths[$item->month - 1],
                "count" => $item->count,
            ]);

        [$labels, $data] = $this->getLabelsAndData($getRentThisYear);

        return [
            "labels" => $labels,
            "data" => $data,
        ];
    }

    public function rentDueThisYear($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $rentQuery = Invoice::where("type", "rent");
        } else {
            $rentQuery = Invoice::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            })->where("type", "rent");
        }

        $getRentThisYear = $rentQuery
            ->select("invoices.month", DB::raw("sum(balance) as count"))
            ->where("year", Carbon::now()->year)
            ->groupBy("month")
            ->get()
            ->map(fn($item) => [
                "month" => $this->allMonths[$item->month - 1],
                "count" => $item->count,
            ]);

        [$labels, $data] = $this->getLabelsAndData($getRentThisYear);

        return [
            "labels" => $labels,
            "data" => $data,
        ];
    }

    /*
     * Water
     */

    public function water($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $waterQuery = Invoice::where("type", "water");

            $waterReadingQuery = new WaterReading;
        } else {
            $waterQuery = Invoice::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            })->where("type", "water");

            $waterReadingQuery = WaterReading::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            });
        }

        $paid = $waterQuery->sum("paid");

        $due = $waterQuery->sum("balance");

        $usageTwoMonthsAgoQuery = clone $waterReadingQuery;
        $usageTwoMonthsAgo = $usageTwoMonthsAgoQuery
            ->where("month", Carbon::now()->subMonths(2)->month)
            ->where("year", Carbon::now()->year)
            ->sum("usage");

        $usageLastMonthQuery = clone $waterReadingQuery;
        $usageLastMonth = $usageLastMonthQuery
            ->where("month", Carbon::now()->subMonth()->month)
            ->where("year", Carbon::now()->year)
            ->sum("usage");

        return [
            "paid" => $paid,
            "due" => $due,
            "total" => number_format($paid + $due),
            "usageTwoMonthsAgo" => $usageTwoMonthsAgo,
            "usageLastMonth" => $usageLastMonth,
            "percentage" => $this->percentage($paid, $due),
            "paidThisYear" => $this->waterPaidThisYear($propertyIds),
            "unpaidThisYear" => $this->waterDueThisYear($propertyIds),
        ];
    }

    public function waterPaidThisYear($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $waterQuery = Invoice::where("type", "water");
        } else {
            $waterQuery = Invoice::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            })->where("type", "water");
        }

        $getRentThisYear = $waterQuery
            ->select("invoices.month", DB::raw("sum(paid) as count"))
            ->where("year", Carbon::now()->year)
            ->groupBy("month")
            ->get()
            ->map(fn($item) => [
                "month" => $this->allMonths[$item->month - 1],
                "count" => $item->count,
            ]);

        [$labels, $data] = $this->getLabelsAndData($getRentThisYear);

        return [
            "labels" => $labels,
            "data" => $data,
        ];
    }

    public function waterDueThisYear($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $waterQuery = Invoice::where("type", "water");
        } else {
            $waterQuery = Invoice::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            })->where("type", "water");
        }

        $getRentThisYear = $waterQuery
            ->select("invoices.month", DB::raw("sum(balance) as count"))
            ->where("year", Carbon::now()->year)
            ->groupBy("month")
            ->get()
            ->map(fn($item) => [
                "month" => $this->allMonths[$item->month - 1],
                "count" => $item->count,
            ]);

        [$labels, $data] = $this->getLabelsAndData($getRentThisYear);

        return [
            "labels" => $labels,
            "data" => $data,
        ];
    }

    /*
     * Service Charge
     */

    public function serviceCharge($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $serviceChargeQuery = Invoice::where("type", "service");
        } else {
            $serviceChargeQuery = Invoice::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            })->where("type", "service");
        }

        $paid = $serviceChargeQuery
            ->sum("paid");

        $due = $serviceChargeQuery
            ->sum("balance");

        return [
            "paid" => $paid,
            "due" => $due,
            "total" => number_format($paid + $due),
            "percentage" => $this->percentage($paid, $due),
            "paidThisYear" => $this->serviceChargePaidThisYear($propertyIds),
            "unpaidThisYear" => $this->serviceChargeDueThisYear($propertyIds),
        ];
    }

    public function serviceChargePaidThisYear($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $serviceChargeQuery = Invoice::where("type", "service");
        } else {
            $serviceChargeQuery = Invoice::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            })->where("type", "service");
        }

        $getRentThisYear = $serviceChargeQuery
            ->select("invoices.month", DB::raw("sum(paid) as count"))
            ->where("year", Carbon::now()->year)
            ->groupBy("month")
            ->get()
            ->map(fn($item) => [
                "month" => $this->allMonths[$item->month - 1],
                "count" => $item->count,
            ]);

        [$labels, $data] = $this->getLabelsAndData($getRentThisYear);

        return [
            "labels" => $labels,
            "data" => $data,
        ];
    }

    public function serviceChargeDueThisYear($propertyIds)
    {
        $isSuper = in_array("All", $propertyIds);

        if ($isSuper) {
            $serviceChargeQuery = Invoice::where("type", "service");
        } else {
            $serviceChargeQuery = Invoice::whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            })->where("type", "service");
        }

        $getRentThisYear = $serviceChargeQuery
            ->select("invoices.month", DB::raw("sum(balance) as count"))
            ->where("year", Carbon::now()->year)
            ->groupBy("month")
            ->get()
            ->map(fn($item) => [
                "month" => $this->allMonths[$item->month - 1],
                "count" => $item->count,
            ]);

        [$labels, $data] = $this->getLabelsAndData($getRentThisYear);

        return [
            "labels" => $labels,
            "data" => $data,
        ];
    }

    public function getLabelsAndData($queriedData)
    {
        $allMonths = $this->allMonths;

        // Extract the months from your collection
        $existingMonths = $queriedData->pluck("month")->toArray();

        // Fill missing months with default count of zero
        $missingMonths = array_diff($allMonths, $existingMonths);
        $missingMonthsSetToZero = collect($missingMonths)
            ->map(fn($month) => [
                "month" => $month,
                "count" => 0,
            ])->toArray();

        // Merge existing data with the missing months filled with default count
        $mergedData = $queriedData
            ->concat($missingMonthsSetToZero)
            ->sortBy(function ($item) use ($allMonths) {
                return array_search($item["month"], $allMonths);
            })
            ->values();

        $labels = $mergedData->map(fn($item) => $item["month"]);
        $data = $mergedData->map(fn($item) => $item["count"]);

        return [$labels, $data];
    }

    // Calculate Percentage
    public function percentage($first, $second)
    {
        // Resolve for Division by Zero
        if ($first == 0) {
            return 0;
        }

        $denominator = $first + $second;

        $percentage = $first / $denominator * 100;

        // Determine if percentage has decimal places
        $decimalPlaces = floor($percentage) == $percentage ? 0 : 1;

        return number_format($percentage, $decimalPlaces);
    }

    /*
     * Search
     */
    public function search($query, $request)
    {
        $propertyId = explode(",", $request->propertyId);

        if ($request->filled("propertyId")) {
            $query = $query->whereIn("property_id", $propertyId);
        }

        if ($request->filled("name")) {
            $query = $query->where("name", "LIKE", "%" . $request->name . "%");
        }

        if ($request->filled("type")) {
            $query = $query->where("type", $request->type);
        }

        return $query;
    }
}

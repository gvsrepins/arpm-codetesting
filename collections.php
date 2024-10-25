<?php
$employees = [
    ['name' => 'John', 'city' => 'Dallas'],
    ['name' => 'Jane', 'city' => 'Austin'],
    ['name' => 'Jake', 'city' => 'Dallas'],
    ['name' => 'Jill', 'city' => 'Dallas'],
];

$offices = [
    ['office' => 'Dallas HQ', 'city' => 'Dallas'],
    ['office' => 'Dallas South', 'city' => 'Dallas'],
    ['office' => 'Austin Branch', 'city' => 'Austin'],
];

$output = [
    "Dallas" => [
        "Dallas HQ" => ["John", "Jake", "Jill"],
        "Dallas South" => ["John", "Jake", "Jill"],
    ],
    "Austin" => [
        "Austin Branch" => ["Jane"],
    ],
];

// write elegant code using collections to generate the $output array. 
//your code goes here..

$employeesColl = collect($employees);
$officesColl = collect($offices);

$output = $employeesColl->groupBy('city')
    ->map(function ($employees, $city) use ($officesColl) {
        return $officesColl->where('city', $city)
            ->mapWithKeys(function ($office) use ($employees) {
                return [$office['office'] => $employees->pluck('name')->toArray()];
            });
    });

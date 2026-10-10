<?php

$generic = [
    'allowed_types' => ['quiz', 'activity', 'project', 'exam'],
    'default_type' => 'activity',
    'default_max_score' => 100,
    'rubric_required_types' => [],
    'passing_percentage' => 75,
    'highest_grade' => 1,
    'passing_grade' => 3,
    'failing_grade' => 5,
    'categories' => [
        ['name' => 'Class Standing', 'assessment_type' => 'activity', 'weight' => 20, 'color' => '#f59e0b'],
        ['name' => 'Requirements', 'assessment_type' => 'project', 'weight' => 30, 'color' => '#db2777'],
        ['name' => 'Term Test', 'assessment_type' => 'exam', 'weight' => 30, 'color' => '#16a34a'],
        ['name' => 'Quizzes', 'assessment_type' => 'quiz', 'weight' => 20, 'color' => '#2563eb'],
    ],
];

return [
    'types' => [
        'activity' => 'Activity',
        'project' => 'Project',
        'quiz' => 'Quiz',
        'exam' => 'Exam',
    ],
    'default' => $generic,
    'profiles' => [
        'CWTS' => $generic,
        'LTS' => [
            'allowed_types' => ['activity', 'project', 'quiz'],
            'default_type' => 'activity',
            'default_max_score' => 100,
            'rubric_required_types' => ['activity', 'project'],
            'passing_percentage' => 75,
            'highest_grade' => 1,
            'passing_grade' => 3,
            'failing_grade' => 5,
            'categories' => [
                ['name' => 'Literacy Activities', 'assessment_type' => 'activity', 'weight' => 30, 'color' => '#f59e0b'],
                ['name' => 'Teaching Projects', 'assessment_type' => 'project', 'weight' => 45, 'color' => '#db2777'],
                ['name' => 'Knowledge Checks', 'assessment_type' => 'quiz', 'weight' => 25, 'color' => '#2563eb'],
            ],
        ],
        'ROTC' => [
            'allowed_types' => ['activity', 'quiz', 'exam'],
            'default_type' => 'activity',
            'default_max_score' => 100,
            'rubric_required_types' => ['activity'],
            'passing_percentage' => 75,
            'highest_grade' => 1,
            'passing_grade' => 3,
            'failing_grade' => 5,
            'categories' => [
                ['name' => 'Drills and Practicum', 'assessment_type' => 'activity', 'weight' => 40, 'color' => '#ea580c'],
                ['name' => 'Military Knowledge', 'assessment_type' => 'quiz', 'weight' => 25, 'color' => '#2563eb'],
                ['name' => 'Written and Practical Exams', 'assessment_type' => 'exam', 'weight' => 35, 'color' => '#16a34a'],
            ],
        ],
    ],
];

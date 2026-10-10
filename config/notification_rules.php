<?php

return [
    'announcement' => [
        'name' => 'Announcements',
        'description' => 'Notifies the intended portal audience when an announcement is published.',
        'title_template' => '{announcement_title}',
        'body_template' => '{announcement_body}',
        'placeholders' => ['announcement_title', 'announcement_body', 'author_name'],
    ],
    'message' => [
        'name' => 'Messages',
        'description' => 'Notifies recipients about unread direct and group messages.',
        'title_template' => 'New message from {sender_name}',
        'body_template' => '{message_body}',
        'placeholders' => ['sender_name', 'message_body', 'group_name'],
    ],
    'learning_material' => [
        'name' => 'New Learning Materials',
        'description' => 'Notifies relevant students and staff when learning material is published.',
        'title_template' => 'New learning material',
        'body_template' => '{material_title} is now available in Learning Materials.',
        'placeholders' => ['material_title', 'component_code', 'section_code'],
    ],
    'assessment' => [
        'name' => 'New Assessments',
        'description' => 'Notifies the assigned section and staff when an assessment is published.',
        'title_template' => 'New assessment',
        'body_template' => '{assessment_title} is now available in Assessments.',
        'placeholders' => ['assessment_title', 'component_code', 'section_code'],
    ],
    'late_attendance' => [
        'name' => 'Late Attendance',
        'description' => 'Alerts the student and relevant staff when attendance is marked late.',
        'title_template' => 'Late attendance update',
        'body_template' => '{student_name} was marked LATE for {session_title}.',
        'placeholders' => ['student_name', 'session_title', 'status', 'section_code'],
    ],
    'absent_attendance' => [
        'name' => 'Absent Attendance',
        'description' => 'Alerts the student and relevant staff when attendance is marked absent.',
        'title_template' => 'Absent attendance update',
        'body_template' => '{student_name} was marked ABSENT for {session_title}.',
        'placeholders' => ['student_name', 'session_title', 'status', 'section_code'],
    ],
];

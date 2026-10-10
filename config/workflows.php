<?php

return [
    'registration_review' => [
        'name' => 'Registration Review',
        'description' => 'Controls validation, correction handling, and automatic registration status transitions.',
        'steps' => [
            'validate_files' => ['name' => 'Validate submitted files', 'description' => 'Block approval when the COR or formal photo is missing or invalid.', 'enabled' => true, 'mode' => 'manual'],
            'require_correction_notes' => ['name' => 'Require correction notes', 'description' => 'Require an explanation when any registration document needs correction.', 'enabled' => true, 'mode' => 'manual'],
            'automatic_status_transition' => ['name' => 'Update registration status', 'description' => 'Automatically derive the registration status from both document decisions.', 'enabled' => true, 'mode' => 'automatic'],
        ],
    ],
    'document_verification' => [
        'name' => 'Document Verification',
        'description' => 'Controls configurable document upload validation and review behavior.',
        'steps' => [
            'validate_upload_rules' => ['name' => 'Enforce configured upload rules', 'description' => 'Apply each document’s configured file types and maximum size.', 'enabled' => true, 'mode' => 'automatic'],
            'require_correction_notes' => ['name' => 'Require correction notes', 'description' => 'Require reviewer guidance for correction outcomes.', 'enabled' => true, 'mode' => 'manual'],
            'lock_approved_uploads' => ['name' => 'Lock approved uploads', 'description' => 'Prevent students from replacing an approved document.', 'enabled' => true, 'mode' => 'automatic'],
        ],
    ],
    'account_creation' => [
        'name' => 'Account Creation',
        'description' => 'Controls automatic student-account provisioning after registration approval.',
        'steps' => [
            'provision_on_approval' => ['name' => 'Provision account on approval', 'description' => 'Create the student account after all registration documents are approved.', 'enabled' => true, 'mode' => 'automatic'],
            'force_password_change' => ['name' => 'Require first-login password change', 'description' => 'Mark generated passwords as temporary.', 'enabled' => true, 'mode' => 'automatic'],
        ],
    ],
    'component_selection' => [
        'name' => 'Component Selection',
        'description' => 'Controls availability and finality of CWTS, LTS, and ROTC selections.',
        'steps' => [
            'enforce_selection_window' => ['name' => 'Enforce selection window', 'description' => 'Honor the NSTP Office open/closed selection setting.', 'enabled' => true, 'mode' => 'automatic'],
            'lock_after_submission' => ['name' => 'Lock selection after submission', 'description' => 'Prevent students from changing a submitted component selection.', 'enabled' => true, 'mode' => 'automatic'],
        ],
    ],
    'rotc_approval' => [
        'name' => 'ROTC Approval',
        'description' => 'Controls advanced ROTC proof and coordinator approval requirements.',
        'steps' => [
            'require_advanced_approval' => ['name' => 'Require advanced ROTC approval', 'description' => 'Route MS-31 and MS-41 selections to a ROTC coordinator.', 'enabled' => true, 'mode' => 'manual'],
            'require_proof' => ['name' => 'Require MS-1 proof', 'description' => 'Require a supporting file before an advanced ROTC request can be submitted.', 'enabled' => true, 'mode' => 'manual'],
        ],
    ],
    'sectioning' => [
        'name' => 'Sectioning',
        'description' => 'Controls which enrollments are eligible and how automated sectioning handles capacity.',
        'steps' => [
            'enrolled_students_only' => ['name' => 'Assign enrolled students only', 'description' => 'Exclude pending-approval enrollments from automated sectioning.', 'enabled' => true, 'mode' => 'automatic'],
            'create_sections_when_full' => ['name' => 'Create sections when capacity is full', 'description' => 'Automatically create a new section when existing sections have no space.', 'enabled' => true, 'mode' => 'automatic'],
        ],
    ],
    'grading' => [
        'name' => 'Grading',
        'description' => 'Controls grade-structure validation and AI-assisted score approval.',
        'steps' => [
            'enforce_weight_total' => ['name' => 'Require category weights to total 100%', 'description' => 'Reject grading structures whose category weights do not total exactly 100%.', 'enabled' => true, 'mode' => 'automatic'],
            'require_ai_approval' => ['name' => 'Require manual AI-score approval', 'description' => 'Keep AI scores as suggestions until a facilitator approves them.', 'enabled' => true, 'mode' => 'manual'],
        ],
    ],
    'archiving' => [
        'name' => 'Archiving',
        'description' => 'Controls restore and permanent-deletion safeguards for archived records.',
        'steps' => [
            'require_archive_before_delete' => ['name' => 'Require archive before deletion', 'description' => 'Block permanent registration deletion until the record is archived.', 'enabled' => true, 'mode' => 'manual'],
            'allow_restore' => ['name' => 'Allow archived records to be restored', 'description' => 'Keep restore actions available for archived records.', 'enabled' => true, 'mode' => 'manual'],
            'require_delete_confirmation' => ['name' => 'Require typed deletion confirmation', 'description' => 'Require the reference code or DELETE phrase before permanent deletion.', 'enabled' => true, 'mode' => 'manual'],
        ],
    ],
];

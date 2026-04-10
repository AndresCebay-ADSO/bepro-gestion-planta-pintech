<?php

use Spatie\Activitylog\Models\Activity;

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /*
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => 180,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject relationship on activities
     * will include soft deleted models.
     */
    'include_soft_deleted_subjects' => false,

    /*
     * This model will be used to log activity.
     * It should implement the Spatie\Activitylog\Contracts\Activity interface
     * and extend Illuminate\Database\Eloquent\Model.
     */
    'activity_model' => Activity::class,

    /*
     * This is the name of the table that will be used by the Activity model.
     */
    'table_name' => 'activity_logs',

    /*
     * These attributes will be excluded from logging for all models.
     * Model-specific exclusions via logExcept() are merged with these.
     */
    'default_except_attributes' => ['password', 'password_confirmation', 'remember_token', 'api_token', 'two_factor_secret', 'two_factor_recovery_codes'],
];

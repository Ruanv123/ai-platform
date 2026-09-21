<?php

return [
    'max_steps_per_turn' => (int) env('FLOW_MAX_STEPS_PER_TURN', 50),
    'max_subflow_depth' => (int) env('FLOW_MAX_SUBFLOW_DEPTH', 5),
    'execution_ttl_days' => (int) env('FLOW_EXECUTION_TTL_DAYS', 30),
    'max_active_executions_per_team' => (int) env('FLOW_MAX_ACTIVE_EXECUTIONS', 100),
    'wait_reconciliation_minutes' => (int) env('FLOW_WAIT_RECONCILIATION_MINUTES', 1),
    'http_retry_times' => (int) env('FLOW_HTTP_RETRY_TIMES', 2),
];

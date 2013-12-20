call extend(g:php_builtin_functions, {
\ 'ftok(': 'string $pathname, string $proj | int',
\ 'msg_get_queue(': 'int $key [, int $perms = 0666] | resource',
\ 'msg_queue_exists(': 'int $key | bool',
\ 'msg_receive(': 'resource $queue, int $desiredmsgtype, int &$msgtype, int $maxsize, mixed &$message [, bool $unserialize = true [, int $flags = 0 [, int &$errorcode]]] | bool',
\ 'msg_remove_queue(': 'resource $queue | bool',
\ 'msg_send(': 'resource $queue, int $msgtype, mixed $message [, bool $serialize = true [, bool $blocking = true [, int &$errorcode]]] | bool',
\ 'msg_set_queue(': 'resource $queue, array $data | bool',
\ 'msg_stat_queue(': 'resource $queue | array',
\ 'sem_acquire(': 'resource $sem_identifier | bool',
\ 'sem_get(': 'int $key [, int $max_acquire = 1 [, int $perm = 0666 [, int $auto_release = 1]]] | resource',
\ 'sem_release(': 'resource $sem_identifier | bool',
\ 'sem_remove(': 'resource $sem_identifier | bool',
\ 'shm_attach(': 'int $key [, int $memsize [, int $perm = 0666]] | resource',
\ 'shm_detach(': 'resource $shm_identifier | bool',
\ 'shm_get_var(': 'resource $shm_identifier, int $variable_key | mixed',
\ 'shm_has_var(': 'resource $shm_identifier, int $variable_key | bool',
\ 'shm_put_var(': 'resource $shm_identifier, int $variable_key, mixed $variable | bool',
\ 'shm_remove_var(': 'resource $shm_identifier, int $variable_key | bool',
\ 'shm_remove(': 'resource $shm_identifier | bool',
\ })
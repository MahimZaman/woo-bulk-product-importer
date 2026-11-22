<?php

namespace WBPI\Import;

use WBPI\Core\Helpers;

defined('ABSPATH') || exit;

class Manager
{
    private Logger $logger;
    private Product $product;

    private string $batchTable;
    private string $queueTable;
    private int $chunkSize = 25;

    public function __construct()
    {
        global $wpdb;

        $this->logger = new Logger();
        $this->product = new Product();

        $this->batchTable = $wpdb->prefix . 'wbpi_batches';
        $this->queueTable = $wpdb->prefix . 'wbpi_queue';
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'cronSchedules']);
        add_action('wbpi_process_queue', [$this, 'processQueue']);
    }

    public function cronSchedules(array $schedules): array
    {
        $schedules['wbpi_every_minute'] = [
            'interval' => 60,
            'display'  => __('Every Minute', 'wbpi')
        ];

        return $schedules;
    }

    public function createBatch(array $products): array
    {
        global $wpdb;

        $batchKey = Helpers::batchKey();

        $wpdb->insert(
            $this->batchTable,
            [
                'batch_key'   => $batchKey,
                'status'      => 'queued',
                'total'       => count($products),
                'processed'   => 0,
                'failed'      => 0,
                'created_at'  => Helpers::now(),
            ]
        );

        $batchId = (int) $wpdb->insert_id;

        foreach ($products as $product) {

            $wpdb->insert(
                $this->queueTable,
                [
                    'batch_id'   => $batchId,
                    'sku'        => $product['sku'] ?? '',
                    'payload'    => Helpers::json($product),
                    'status'     => 'pending',
                    'attempts'   => 0,
                    'created_at' => Helpers::now(),
                ]
            );
        }

        if (!wp_next_scheduled('wbpi_process_queue')) {
            wp_schedule_event(
                time() + 5,
                'wbpi_every_minute',
                'wbpi_process_queue'
            );
        }

        $this->logger->info(
            'Batch created.',
            [
                'batch_id' => $batchId,
                'batch_key' => $batchKey,
                'products' => count($products)
            ]
        );

        return [
            'id' => $batchId,
            'batch_key' => $batchKey,
            'total' => count($products)
        ];
    }

    public function pauseBatch(string $batchKey): bool
    {
        global $wpdb;

        $batch = $this->getBatch($batchKey);

        if (!$batch || !in_array($batch->status, ['queued', 'processing'], true)) {
            return false;
        }

        $updated = $wpdb->update(
            $this->batchTable,
            [
                'status' => 'paused'
            ],
            [
                'id' => $batch->id
            ]
        );

        if ($updated) {
            $this->logger->info('Batch paused.', [
                'batch_id' => $batch->id,
                'batch_key' => $batchKey
            ]);
        }

        return (bool) $updated;
    }

    public function resumeBatch(string $batchKey): bool
    {
        global $wpdb;

        $batch = $this->getBatch($batchKey);

        if (!$batch || $batch->status !== 'paused') {
            return false;
        }

        $updated = $wpdb->update(
            $this->batchTable,
            [
                'status' => 'processing'
            ],
            [
                'id' => $batch->id
            ]
        );

        if (!wp_next_scheduled('wbpi_process_queue')) {
            wp_schedule_event(
                time() + 5,
                'wbpi_every_minute',
                'wbpi_process_queue'
            );
        }

        if ($updated) {
            $this->logger->info('Batch resumed.', [
                'batch_id' => $batch->id,
                'batch_key' => $batchKey
            ]);
        }

        return (bool) $updated;
    }

    public function stopBatch(string $batchKey): bool
    {
        global $wpdb;

        $batch = $this->getBatch($batchKey);

        if (!$batch) {
            return false;
        }

        $wpdb->update(
            $this->batchTable,
            [
                'status' => 'cancelled',
                'completed_at' => Helpers::now()
            ],
            [
                'id' => $batch->id
            ]
        );

        $wpdb->query(
            $wpdb->prepare(
                "
            UPDATE {$this->queueTable}
            SET status='cancelled'
            WHERE batch_id=%d
            AND status IN('pending','processing')
            ",
                $batch->id
            )
        );

        delete_transient('wbpi_import_lock');

        $this->logger->warning('Batch cancelled.', [
            'batch_id' => $batch->id,
            'batch_key' => $batchKey
        ]);

        return true;
    }

    public function restartBatch(string $batchKey): bool
    {
        global $wpdb;

        $batch = $this->getBatch($batchKey);

        if (!$batch) {
            return false;
        }

        $wpdb->query(
            $wpdb->prepare(
                "
            UPDATE {$this->queueTable}
            SET
                status='pending',
                attempts=0,
                started_at=NULL,
                completed_at=NULL,
                error=NULL
            WHERE batch_id=%d
            ",
                $batch->id
            )
        );

        $wpdb->update(
            $this->batchTable,
            [
                'status' => 'queued',
                'processed' => 0,
                'failed' => 0,
                'started_at' => null,
                'completed_at' => null
            ],
            [
                'id' => $batch->id
            ]
        );

        delete_transient('wbpi_import_lock');

        $this->logger->info('Batch restarted.', [
            'batch_id' => $batch->id,
            'batch_key' => $batchKey
        ]);

        return true;
    }

    public function processQueue(): void
    {
        global $wpdb;

        if (get_transient('wbpi_import_lock')) {
            return;
        }

        set_transient('wbpi_import_lock', 1, 300);
        try {
            $batch = $wpdb->get_row("
                SELECT *
                FROM {$this->batchTable}
                WHERE status IN ('queued','processing')
                ORDER BY id ASC
                LIMIT 1
            ");

            if (!$batch) {
                delete_transient('wbpi_import_lock');
                return;
            }

            if ($batch->status === 'paused') {
                delete_transient('wbpi_import_lock');
                return;
            }

            if ($batch->status === 'cancelled') {
                delete_transient('wbpi_import_lock');
                return;
            }

            if ($batch->status === 'queued') {

                $wpdb->update(
                    $this->batchTable,
                    [
                        'status' => 'processing',
                        'started_at' => Helpers::now()
                    ],
                    [
                        'id' => $batch->id
                    ]
                );

                $batch->status = 'processing';
            }

            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "
            SELECT *
            FROM {$this->queueTable}
            WHERE batch_id=%d
            AND status='pending'
            ORDER BY id ASC
            LIMIT %d
            ",
                    $batch->id,
                    $this->chunkSize
                )
            );

            foreach ($items as $item) {

                $wpdb->update(
                    $this->queueTable,
                    [
                        'status' => 'processing',
                        'started_at' => Helpers::now(),
                        'attempts' => $item->attempts + 1
                    ],
                    [
                        'id' => $item->id
                    ]
                );

                $product = Helpers::decode($item->payload);

                $result = $this->product->sync($product);

                if ($result) {

                    $wpdb->update(
                        $this->queueTable,
                        [
                            'status' => 'completed',
                            'completed_at' => Helpers::now()
                        ],
                        [
                            'id' => $item->id
                        ]
                    );

                    $wpdb->query(
                        $wpdb->prepare(
                            "
                    UPDATE {$this->batchTable}
                    SET processed=processed+1
                    WHERE id=%d
                    ",
                            $batch->id
                        )
                    );
                } else {

                    $failed = ($item->attempts + 1) >= 3;

                    $wpdb->update(
                        $this->queueTable,
                        [
                            'status' => $failed ? 'failed' : 'pending'
                        ],
                        [
                            'id' => $item->id
                        ]
                    );

                    if ($failed) {

                        $wpdb->query(
                            $wpdb->prepare(
                                "
                        UPDATE {$this->batchTable}
                        SET failed=failed+1
                        WHERE id=%d
                        ",
                                $batch->id
                            )
                        );
                    }
                }
            }

            $remaining = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "
            SELECT COUNT(*)
            FROM {$this->queueTable}
            WHERE batch_id=%d
            AND status IN ('pending','processing')
            ",
                    $batch->id
                )
            );

            if ($remaining === 0) {

                $wpdb->update(
                    $this->batchTable,
                    [
                        'status' => 'completed',
                        'completed_at' => Helpers::now()
                    ],
                    [
                        'id' => $batch->id
                    ]
                );

                $this->logger->info(
                    'Batch completed.',
                    [
                        'batch_id' => $batch->id
                    ]
                );
            }
        } finally {

            delete_transient('wbpi_import_lock');
        }
    }

    public function getBatch(string $batchKey): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->batchTable}
                WHERE batch_key=%s
                LIMIT 1
                ",
                $batchKey
            )
        );
    }

    public function getProgress(string $batchKey = ''): array
    {
        global $wpdb;

        if (empty($batchKey)) {
            $batch = $wpdb->get_row("
            SELECT *
            FROM {$this->batchTable}
            WHERE status IN ('queued','processing','paused')
            ORDER BY id ASC
            LIMIT 1
        ");
        } else {
            $batch = $this->getBatch($batchKey);
        }

        if (!$batch) {
            return [
                'running' => false,
                'status' => 'idle'
            ];
        }

        $completed = (int) $batch->processed + (int) $batch->failed;
        $remaining = max(0, (int) $batch->total - $completed);

        $percentage = $batch->total > 0
            ? round(($completed / $batch->total) * 100, 2)
            : 0;

        $pending = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->queueTable} WHERE batch_id=%d AND status='pending'",
                $batch->id
            )
        );

        $processing = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->queueTable} WHERE batch_id=%d AND status='processing'",
                $batch->id
            )
        );

        $elapsed = 0;
        $speed = 0;
        $eta = 0;

        if (!empty($batch->started_at)) {

            $elapsed = max(
                1,
                current_time('timestamp') - strtotime($batch->started_at)
            );

            if ($batch->processed > 0) {

                $speed = round($batch->processed / ($elapsed / 60), 2);

                if ($speed > 0) {
                    $eta = (int) round(($remaining / $speed) * 60);
                }
            }
        }

        return [
            'running'      => in_array($batch->status, ['queued', 'processing', 'paused'], true),
            'batch_key'    => $batch->batch_key,
            'status'       => $batch->status,
            'total'        => (int) $batch->total,
            'processed'    => (int) $batch->processed,
            'failed'       => (int) $batch->failed,
            'pending'      => $pending,
            'processing'   => $processing,
            'remaining'    => $remaining,
            'percentage'   => $percentage,
            'elapsed'      => $elapsed,
            'speed'        => $speed,
            'eta'          => $eta
        ];
    }

    public function currentBatch(): ?object
    {
        global $wpdb;

        return $wpdb->get_row("
        SELECT *
        FROM {$this->batchTable}
        WHERE status IN ('queued','processing','paused')
        ORDER BY id ASC
        LIMIT 1
    ");
    }

    public function currentBatchKey(): ?string
    {
        $batch = $this->currentBatch();

        return $batch ? $batch->batch_key : null;
    }

    public function hasRunningBatch(): bool
    {
        return $this->currentBatch() !== null;
    }

    public function queueStatus(string $batchKey): array
    {
        global $wpdb;

        $batch = $this->getBatch($batchKey);

        if (!$batch) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT
                sku,
                status,
                attempts,
                started_at,
                completed_at
            FROM {$this->queueTable}
            WHERE batch_id=%d
            ORDER BY id ASC
            ",
                $batch->id
            ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    public function batchHistory(int $limit = 20): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT
                id,
                batch_key,
                status,
                total,
                processed,
                failed,
                created_at,
                started_at,
                completed_at
            FROM {$this->batchTable}
            ORDER BY id DESC
            LIMIT %d
            ",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }
}

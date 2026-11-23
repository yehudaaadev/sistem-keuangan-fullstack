<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/** Backup MySQL harian ke storage/app/backup dan buang backup yang lebih tua dari N hari. */
class BackupDatabase extends Command
{
    protected $signature = 'backup:db {--simpan=14 : Lama penyimpanan (hari)}';

    protected $description = 'Backup database MySQL memakai mysqldump';

    public function handle(): int
    {
        $c = config('database.connections.'.config('database.default'));
        if (($c['driver'] ?? null) !== 'mysql') {
            $this->error('Backup hanya mendukung MySQL.');

            return self::FAILURE;
        }

        $dir = storage_path('app/backup');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/'.$c['database'].'-'.now()->format('Ymd-His').'.sql';

        $cmd = [
            env('MYSQLDUMP_PATH', 'mysqldump'), '--single-transaction', '--routines',
            '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username'],
            '--result-file='.$file, $c['database'],
        ];
        // Sandi lewat environment agar tidak muncul di daftar proses.
        $proses = new Process($cmd, null, ['MYSQL_PWD' => (string) ($c['password'] ?? '')]);
        $proses->setTimeout(300)->run();

        if (! $proses->isSuccessful() || ! is_file($file) || filesize($file) === 0) {
            @unlink($file);
            $this->error('Backup gagal: '.trim($proses->getErrorOutput() ?: 'mysqldump tidak ditemukan (atur MYSQLDUMP_PATH di .env).'));

            return self::FAILURE;
        }

        foreach (File::glob($dir.'/*.sql') as $lama) {
            if (filemtime($lama) < now()->subDays((int) $this->option('simpan'))->getTimestamp()) {
                @unlink($lama);
            }
        }
        $this->info('Backup tersimpan: '.$file);

        return self::SUCCESS;
    }
}

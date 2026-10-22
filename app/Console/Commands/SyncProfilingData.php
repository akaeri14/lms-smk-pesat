<?php

namespace App\Console\Commands;

use App\Services\ProfilingApiService;
use Illuminate\Console\Command;
use Throwable;

class SyncProfilingData extends Command
{
    protected $signature = 'sync:profiling-data
                            {--fresh : Abaikan cache dan ambil data terbaru dari API}
                            {--students : Hanya sinkronkan siswa}
                            {--teachers : Hanya sinkronkan guru}';

    protected $description = 'Sinkronkan data siswa dan guru dari External Profiling API ke LMS.';

    public function handle(ProfilingApiService $profiling): int
    {
        $onlyStudents = $this->option('students');
        $onlyTeachers = $this->option('teachers');
        $syncStudents = $onlyStudents || ! $onlyTeachers;
        $syncTeachers = $onlyTeachers || ! $onlyStudents;

        $this->info('Memulai sinkronisasi Profiling API...');

        try {
            $result = $profiling->syncUsers((bool) $this->option('fresh'), $syncStudents, $syncTeachers);
        } catch (Throwable $exception) {
            $this->error('Gagal: '.$exception->getMessage());
            return self::FAILURE;
        }

        foreach ($profiling->warnings() as $warning) {
            $this->warn($warning);
        }

        $this->table(['Jenis', 'Tersinkron', 'Dibuat', 'Diperbarui', 'Gagal', 'Dilewati'], [
            ['Siswa', $result['student_stats']['synced'], $result['student_stats']['created'], $result['student_stats']['updated'], $result['student_stats']['failed'], $result['student_stats']['skipped']],
            ['Guru', $result['teacher_stats']['synced'], $result['teacher_stats']['created'], $result['teacher_stats']['updated'], $result['teacher_stats']['failed'], $result['teacher_stats']['skipped']],
        ]);
        $this->line("Total baru: {$result['created']} | diperbarui: {$result['updated']} | gagal: {$result['failed']} | dilewati: {$result['skipped']}");
        $this->info('Sinkronisasi selesai.');

        return self::SUCCESS;
    }
}

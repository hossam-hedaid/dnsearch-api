<?php

namespace App\Jobs;

use App\Models\DomainWord;
use App\Models\PendingDeleteDomain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ProcessExpiringDomains implements ShouldQueue
{
    use Queueable;

    protected array $names;

    /**
     * Create a new job instance.
     */
    public function __construct($names)
    {
        $this->names = $names;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $t0 = microtime(true);
        $stats = $this->getNamesStats(array_keys($this->names));

        $t1 = microtime(true);
        logger("[chunk] get stats: " . round($t1 - $t0, 2) . "s");

        $domainRows = [];
        foreach ($stats as $name => $stat) {
            $nameData = $this->names[$name];
            $domainRows[] = [
                'name' => $nameData->domain,
                'expiration_date' => $nameData->expire,
                'grammar_score' => $stat->grammar->score,
                'valid_grammar' => $stat->grammar->label === 'valid',
            ];
        }

        PendingDeleteDomain::upsert(
            $domainRows,
            uniqueBy: ['name'],
            update: ['expiration_date', 'grammar_score', 'valid_grammar']
        );

        $domainIds = PendingDeleteDomain::whereIn('name', array_column($domainRows, 'name'))
            ->pluck('id', 'name'); // ['example.com' => 123, ...]

        $allWords = [];
        foreach ($stats as $stat) {
            foreach ($stat->freq as $word => $freq) {
                $allWords[$word] = $freq; // dedupes automatically via array key
            }
        }

        $wordRows = [];
        foreach ($allWords as $word => $freq) {
            $wordRows[] = [
                'word' => $word,
                'frequency' => $freq
            ];
        }

        if (! empty($wordRows)) {
            DomainWord::upsert(
                $wordRows,
                uniqueBy: ['word'],
                update: ['frequency']
            );
        }

        $wordIds = DomainWord::whereIn('word', array_keys($allWords))
            ->pluck('id', 'word'); // ['shop' => 45, ...]

        $pivotRows = [];
        foreach ($stats as $name => $stat) {
            $domainId = $domainIds[$this->names[$name]->domain];
            $index = 0;
            foreach ($stat->freq as $word => $freq) {
                $pivotRows[] = [
                    'domain_id' => $domainId,
                    'word_id' => $wordIds[$word],
                    'index' => $index,
                ];
                $index++;
            }
        }

        foreach (array_chunk($pivotRows, 1000) as $batch) {
            DB::table('pending_delete_domain_words')->insertOrIgnore($batch);
        }

        $t2 = microtime(true);
        logger("[chunk] DB insert: " . round($t2 - $t1, 2) . "s");

    }

    private function getNamesStats(array $names)
    {
        if(count($names) == 0){
            return [];
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'names_');
        file_put_contents($tmpFile, json_encode($names));
        $proccess = new Process([
            base_path('script/.venv/bin/python3'),
            base_path("script/name_rater.py"),
            $tmpFile
        ]);
        $proccess->setTimeout(null);
        $proccess->run();
        unlink($tmpFile);

        if(! $proccess->isSuccessful()){
            throw new ProcessFailedException($proccess);
        }
        return json_decode($proccess->getOutput());
    }
}

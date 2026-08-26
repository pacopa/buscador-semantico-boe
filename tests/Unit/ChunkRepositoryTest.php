<?php

namespace Tests\Unit;

use App\Services\Boe\ChunkRepository;
use PHPUnit\Framework\TestCase;

final class ChunkRepositoryTest extends TestCase
{
    private string $indexPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexPath = sys_get_temp_dir() . '/boe-chunks-' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->indexPath)) {
            unlink($this->indexPath);
        }

        parent::tearDown();
    }

    public function test_save_many_updates_existing_chunks_by_id(): void
    {
        $repository = new ChunkRepository('json', $this->indexPath, '', '');
        $repository->reset();

        $repository->saveMany([$this->chunk('BOE-A-1#0', 'Texto original')]);
        $repository->saveMany([$this->chunk('BOE-A-1#0', 'Texto actualizado')]);

        $chunks = $repository->all();

        $this->assertCount(1, $chunks);
        $this->assertSame('Texto actualizado', $chunks[0]['text']);
    }

    public function test_save_many_accumulates_different_chunks(): void
    {
        $repository = new ChunkRepository('json', $this->indexPath, '', '');
        $repository->reset();

        $repository->saveMany([$this->chunk('BOE-A-1#0', 'Texto uno')]);
        $repository->saveMany([$this->chunk('BOE-A-2#0', 'Texto dos')]);

        $this->assertSame([
            'store' => 'json',
            'chunks' => 2,
            'documents' => 2,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-01',
        ], $repository->stats());
    }

    /** @return array<string, mixed> */
    private function chunk(string $id, string $text): array
    {
        return [
            'id' => $id,
            'document_id' => explode('#', $id)[0],
            'chunk_index' => 0,
            'text' => $text,
            'embedding' => [1.0, 0.0],
            'source' => [
                'boe_id' => explode('#', $id)[0],
                'title' => 'Documento de prueba',
                'date' => '2026-06-01',
                'url' => 'https://www.boe.es/',
            ],
        ];
    }
}

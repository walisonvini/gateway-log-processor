<?php

namespace Tests\Unit\Reports;

use App\Contracts\Report;
use App\Exceptions\ReportNotWritableException;
use App\Services\Reports\CsvReportWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvReportWriterTest extends TestCase
{
    /**
     * Diretório temporário onde o teste grava os relatórios, removido no tearDown.
     */
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/csv-report-writer-'.uniqid();

        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_it_writes_the_headers_and_the_rows_to_the_report_file(): void
    {
        $report = $this->report(
            filename: 'exemplo.csv',
            headers: ['service_name', 'total_requests'],
            rows: [['ritchie', 3], ['terry', 2]],
        );

        $path = (new CsvReportWriter)->write($report, $this->directory);

        $this->assertSame($this->directory.'/exemplo.csv', $path);
        $this->assertSame(
            "service_name,total_requests\nritchie,3\nterry,2\n",
            file_get_contents($path),
        );
    }

    public function test_it_creates_the_directory_when_it_does_not_exist(): void
    {
        $directory = $this->directory.'/novos/relatorios';

        $report = $this->report(filename: 'exemplo.csv', headers: ['service_name'], rows: [['ritchie']]);

        $path = (new CsvReportWriter)->write($report, $directory);

        $this->assertDirectoryExists($directory);
        $this->assertFileExists($path);
    }

    public function test_it_keeps_the_previous_report_when_the_generation_fails(): void
    {
        $writer = new CsvReportWriter;

        $path = $writer->write(
            $this->report(filename: 'exemplo.csv', headers: ['service_name'], rows: [['ritchie']]),
            $this->directory,
        );

        // Nova geração do mesmo relatório, que falha depois de entregar a primeira linha.
        $failingRows = (function () {
            yield ['terry'];

            throw new RuntimeException('Falha simulada na geração.');
        })();

        try {
            $writer->write(
                $this->report(filename: 'exemplo.csv', headers: ['service_name'], rows: $failingRows),
                $this->directory,
            );

            $this->fail('A geração deveria ter sido interrompida.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha simulada na geração.', $exception->getMessage());
        }

        $this->assertSame("service_name\nritchie\n", file_get_contents($path));
        $this->assertSame(['exemplo.csv'], array_values(array_diff(scandir($this->directory), ['.', '..'])));
    }

    public function test_it_fails_when_the_directory_is_read_only(): void
    {
        if (posix_geteuid() === 0) {
            $this->markTestSkipped('O usuário root consegue gravar mesmo em diretórios somente leitura.');
        }

        $directory = $this->directory.'/somente-leitura';

        mkdir($directory, 0555);

        $this->expectException(ReportNotWritableException::class);
        $this->expectExceptionMessage("Não foi possível gravar relatórios no diretório [{$directory}].");

        (new CsvReportWriter)->write(
            $this->report(filename: 'exemplo.csv', headers: ['service_name'], rows: [['ritchie']]),
            $directory,
        );
    }

    /**
     * Cria um relatório falso, com o conteúdo definido pelo teste.
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<int|float|string>>  $rows
     */
    private function report(string $filename, array $headers, iterable $rows): Report
    {
        return new class($filename, $headers, $rows) implements Report
        {
            public function __construct(
                private string $filename,
                private array $headers,
                private iterable $rows,
            ) {}

            public function filename(): string
            {
                return $this->filename;
            }

            public function headers(): array
            {
                return $this->headers;
            }

            public function rows(): iterable
            {
                return $this->rows;
            }
        };
    }

    /**
     * Remove um diretório e tudo o que há dentro dele.
     */
    private function deleteDirectory(string $directory): void
    {
        foreach (array_diff(scandir($directory), ['.', '..']) as $item) {
            $path = $directory.'/'.$item;

            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}

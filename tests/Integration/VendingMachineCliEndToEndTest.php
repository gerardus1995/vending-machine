<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage: runs the real CLI binary as a subprocess, feeds it
 * commands through STDIN and asserts the rendered STDOUT contract.
 *
 * @internal
 *
 * @coversNothing
 *
 * @group integration
 */
final class VendingMachineCliEndToEndTest extends TestCase
{
    private const STOCKED_MACHINE_SERVICE = 'SERVICE water:5,juice:5,soda:5 5:10,10:10,25:10,100:10';

    public function testRunsTheThreeChallengeExamplesInOneSession(): void
    {
        $input = implode("\n", [
            self::STOCKED_MACHINE_SERVICE,
            '1',        // example 1 session: 1 + 0.25 + 0.25 = 1.50 for Soda
            '0.25',
            '0.25',
            'GET-SODA', // -> SODA (exact payment)
            '0.10',     // example 2 session
            '0.10',
            'RETURN-COIN', // -> 0.10, 0.10
            '1',        // example 3 session: 1.00 for Water (0.65)
            'GET-WATER',   // -> WATER, 0.25, 0.10
            '',
        ]);

        [$exitCode, $stdout] = $this->runBinary($input);

        self::assertSame(0, $exitCode);
        self::assertSame(
            "SERVICE APPLIED\nSODA\n0.10, 0.10\nWATER, 0.25, 0.10\n",
            $stdout
        );
    }

    public function testInsufficientFundsIsRenderedAsAnErrorLine(): void
    {
        [$exitCode, $stdout] = $this->runBinary(implode("\n", [
            self::STOCKED_MACHINE_SERVICE,
            '0.25',
            'GET-JUICE',
            '',
        ]));

        self::assertSame(0, $exitCode);
        self::assertSame(
            "SERVICE APPLIED\nERROR: Insufficient funds for \"Juice\": inserted 0.25, price 1.00\n",
            $stdout
        );
    }

    public function testSessionContinuesAfterAnUnknownCommand(): void
    {
        [$exitCode, $stdout] = $this->runBinary("FOO\n0.25\nRETURN-COIN\n");

        self::assertSame(0, $exitCode);
        self::assertSame(
            "ERROR: unknown command \"FOO\"\n0.25\n",
            $stdout
        );
    }

    public function testEmptyInputExitsCleanlyWithNoOutput(): void
    {
        [$exitCode, $stdout] = $this->runBinary('');

        self::assertSame(0, $exitCode);
        self::assertSame('', $stdout);
    }

    /**
     * Spawns `php bin/vending-machine`, writes $input to its STDIN and returns
     * the process exit code plus everything it wrote to STDOUT.
     *
     * @return array{0: int, 1: string}
     */
    private function runBinary(string $input): array
    {
        $command = [PHP_BINARY, __DIR__.'/../../bin/vending-machine'];

        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        self::assertIsResource($process, 'Failed to start the CLI binary');

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, false === $stdout ? '' : $stdout];
    }
}

<?php

declare(strict_types=1);

namespace WordPressBoost\Transport;

/**
 * STDIO Transport for MCP Protocol
 *
 * Handles reading from STDIN and writing to STDOUT for JSON-RPC 2.0 communication.
 */
class StdioTransport
{
    private $stdin;
    private $stdout;
    private $stderr;

    public function __construct()
    {
        $this->stdin = STDIN;
        $this->stdout = STDOUT;
        $this->stderr = STDERR;
    }

    /**
     * Read a message from STDIN
     *
     * Reads JSON-RPC messages in the format used by MCP (newline-delimited JSON).
     *
     * @return array|null Decoded JSON message or null on EOF
     */
    public function read(): ?array
    {
        $line = fgets($this->stdin);

        if ($line === false) {
            return null;
        }

        $line = trim($line);

        if (empty($line)) {
            return null;
        }

        $data = json_decode($line, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->writeError(-32700, 'Parse error: ' . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Write a message to STDOUT
     *
     * @param array $message The message to send
     */
    public function write(array $message): void
    {
        $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            $this->log('Failed to encode message: ' . json_last_error_msg());
            return;
        }

        fwrite($this->stdout, $json . "\n");
        fflush($this->stdout);
    }

    /**
     * Write an error response
     *
     * @param int $code Error code
     * @param string $message Error message
     * @param mixed $id Request ID (null for notifications)
     */
    public function writeError(int $code, string $message, $id = null): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($id !== null) {
            $response['id'] = $id;
        }

        $this->write($response);
    }

    /**
     * Write a success response
     *
     * @param mixed $result The result data
     * @param mixed $id Request ID
     */
    public function writeResult($result, $id): void
    {
        $this->write([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ]);
    }

    /**
     * Log a message to STDERR (for debugging)
     *
     * @param string $message The message to log
     */
    public function log(string $message): void
    {
        fwrite($this->stderr, "[wordpress-boost] " . $message . "\n");
        fflush($this->stderr);
    }

    /**
     * Check if STDIN is still open
     */
    public function isOpen(): bool
    {
        return !feof($this->stdin);
    }
}

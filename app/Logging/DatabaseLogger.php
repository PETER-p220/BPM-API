<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use App\Models\Log; // Your Log model

class DatabaseLogger extends AbstractProcessingHandler
{
    // Update the method signature to accept Monolog\LogRecord instead of array
    protected function write(LogRecord $record): void
    {
        // Save log data to the 'logs' table
        Log::create([
            'level' => $record->levelName, // Monolog uses `levelName` instead of `level_name`
            'message' => $record->message, // Monolog stores the message here
            'context' => json_encode($record->context), // Store the context as JSON
        ]);
    }
}

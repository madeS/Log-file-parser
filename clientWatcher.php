<?php

class Tailer {

    protected $file = '';

    protected $buffer = '';

    public $lines = [];

    protected $size = 0;

    /**
     * Tailer constructor.
     * @param $fileOrDir
     * @throws Exception
     */
    public function __construct($fileOrDir) {
        if (is_file($fileOrDir)) {
            $this->file = $fileOrDir;
            return;
        } elseif (is_dir($fileOrDir)) {
            $files = scandir($fileOrDir);
            print_r($files);
            sort($files);
            $file = end($files);
            if (is_file($fileOrDir . '/' . $file)) {
                $this->file = $fileOrDir . '/' . $file;
                return;
            }
        }

        throw new \Exception('No file to tail in ' . $fileOrDir);
    }


    public function see($func) {
        while (true) {
            clearstatcache();
            $currentSize = filesize($this->file);
            if ($this->size == $currentSize) {
                usleep(1000000);
                continue;
            }

            $fh = fopen($this->file, "r");
            fseek($fh, $this->size);

            while ($d = fread($fh,1000)) {
                $this->buffer .= $d;
            }

            fclose($fh);
            $this->size = $currentSize;

            $explodedBuffer = explode("\n",$this->buffer);

            for ($i = 0; $i < count($explodedBuffer) - 1; $i++) {
                $this->lines[] = $explodedBuffer[$i];
            }

            $this->buffer = $explodedBuffer[count($explodedBuffer)-1];

            $func($this);
        }
    }

    public function clearLines($laskKey) {
        $count = count($this->lines);
        for($i = 0; $i < $count; $i++) {
            if ($i > $laskKey) {
                break;
            }
            unset($this->lines[$i]);
        }
        $this->lines = array_values($this->lines);
    }
}
$endfile = __DIR__ . '/app/requests/logTailer.json';
$tailer = new Tailer(__DIR__ . '/../../../winClient/BingoBlitz/Bin/Logs/');
$tailer->see(function (Tailer $t) use ($endfile) {
    $lastKeyWithProcessedResponse = 0;
    foreach ($t->lines as $key => $line) {
        echo '.';
        if (strpos($line,'  "response_body":')===0 && isset($t->lines[$key+1])) { // maybe first line of response
            $responseLog = $t->lines[$key-1] . $t->lines[$key] . $t->lines[$key+1];
            $responseLogArray = json_decode($responseLog,true);
            $response = $responseLogArray['response_body'];
            $responseJSON = json_encode(json_decode($response,true),JSON_PRETTY_PRINT);
            file_put_contents($endfile,
                (
                    "\n,"
                    .'{"time":"'.date('Y-m-d H:i:s').'"},'."\n"
                    . $responseJSON
                ),
                FILE_APPEND
            );
            $lastKeyWithProcessedResponse = $key + 1;
            echo 'R';
        }
    }
    $t->clearLines($lastKeyWithProcessedResponse);
});

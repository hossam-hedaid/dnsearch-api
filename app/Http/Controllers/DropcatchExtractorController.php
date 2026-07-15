<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExpiringDomains;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DropcatchExtractorController extends Controller
{
    public function store(request $request)
    {
        $request->validate([
            'file' => [
                'required', 'file', 'mimes:zip', 'max:10240',
                'mimetypes:application/zip,application/x-zip-compressed,multipart/x-zip'
            ],
        ]);

        $zip = $request->file('file');
        $archive = new ZipArchive();
        if($archive->open($zip->getRealPath()) !== true){
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be opened.',
            ]);
        }

        $csvName = null;

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = $archive->getNameIndex($i);

            if (str_ends_with(strtolower($name), '.csv')) {
                $csvName = $name;
                break;
            }
        }

        if ($csvName === null) {
            $archive->close();
            throw ValidationException::withMessages([
                'file' => 'The zip file must contain at least one CSV file.',
            ]);
        }

        $stream = $archive->getStream($csvName);

        if ($stream === false) {
            $archive->close();
            throw ValidationException::withMessages([
                'file' => 'The CSV file inside the zip could not be read.',
            ]);
        }

        $names = [];
        if($row = fgetcsv($stream)){
            $header = $row;
            for($i = 0; $i < count($header); $i++){
                $header[$i] = strtolower(str_replace(" ","",$header[$i]));
            }

            if(! in_array("domain", $header)){
                throw ValidationException::withMessages([
                    'file' => 'The csv file doesn\'t contain a domain column',
                ]);
            }

            if(! in_array("dropdate", $header)){
                throw ValidationException::withMessages([
                    'file' => 'The csv file doesn\'t contain a domain column',
                ]);
            }

            while ($row = fgetcsv($stream)){
                $row = array_combine($header, $row);
                [$name, $ext] = explode(".", $row['domain']);
                $name = strtolower($name);
                if($ext != "com"){
                    continue;
                }
                if(preg_match('/^[a-z]+$/i', $name) == 0){
                    continue;
                }
                $names[$name] = (object)[
                    'domain' => $row['domain'],
                    'expire' => $row['dropdate'],
                ];
            }
        }
        $archive->close();
        $chunks = array_chunk($names, 10000, true);
        foreach ($chunks as $chunk) {
            ProcessExpiringDomains::dispatch($chunk);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AutoDirtibuter;
use App\Models\AutoDirtibuterData;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AutoDirtibuterController extends Controller
{

    /**
 *  @OA\Get(
 *       path="/auto-distributer",
 *       tags={"AutoDistribuetr"},
 *       summary="Get all AutoDistribuetr",
 *       description="Get list of all AutoDistribuetr",
 *       @OA\Response(response=200, description="AutoDistribuetr retrieved successfully")
 *   )
 */


    // Display list of uploaded files...........................................................................................................
    public function index()
    {
        $files = AutoDirtibuter::with('user')->latest()->get();
        return view('autodisributers.index', compact('files'));
    }

    // Show form to upload CSV...................................................................................................................
    public function create()
    {
        return view('autodisributers.create');
    }

    // Store File & data .........................................................................................................................
    public function store(Request $request)
    {

        $request->validate([
            'file_name' => 'required|string|max:255',
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $randomFileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
        if ($file->getSize() == 0) {
            return redirect()->route('autodistributers.index')->with('error', 'The uploaded file is empty.');
        }


        $autoDailer = AutoDirtibuter::create([
            'file_name' => $request->input('file_name'),
            'uploaded_by' => Auth::id(),
        ]);

        $filePath = $file->storeAs('csv_files', $randomFileName, 'public');
        $autoDailer->update(['file_path' => $filePath]);
        $fileContent = file($file->getRealPath());
        $isValidStructure = true;

        foreach ($fileContent as $line) {
            $data = str_getcsv($line);
            if (count($data) !== 3) {
                $isValidStructure = false;
                break;
            }

            AutoDirtibuterData::create([
                'auto_dirtibuter_id' => $autoDailer->id,
                'mobile' => $data[0],
                'provider_name' => $data[1],
                'extension' => $data[2],
            ]);

            // Active Log Report...............................
            ActivityLog::create([
                'user_id' => Auth::id(),
                'operation' => 'create',
                'file_type' => 'Auto Distributer',
                'file_name' => $request->input('file_name'),
                'operation_time' => now(),
            ]);
        }

        if (!$isValidStructure) {
            Storage::disk('public')->delete($filePath);
            return redirect()->route('autodistributers.index')->with('error', 'File structure is not correct. Please ensure each row has 3 columns. The File is Empty, please delete it and upload file in correct structure');
        }

        return redirect()->route('autodistributers.index')->with('success', 'File uploaded successfully.');
    }

     // Edit file name..............................................................................................................................
     public function edit($id)
     {
         $file = AutoDirtibuter::findOrFail($id);
         return view('autodisributers.edit', compact('file'));
     }

      // Update file name............................................................................................................................
    public function update(Request $request, $id)
    {
        $request->validate([
            'file_name' => 'required|string|max:255',
        ]);

        $file = AutoDirtibuter::findOrFail($id);
        $file->update(['file_name' => $request->file_name]);

         // Active Log Report...............................
         ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'update',
            'file_type' => 'Auto Distributer',
            'file_name' => $request->input('file_name'),
            'operation_time' => now(),
        ]);

        return redirect()->route('autodistributers.index')->with('success', 'File name updated successfully.');
    }

    // Show details of a specific uploaded file......................................................................................................
    public function show($id)
    {
        $file = AutoDirtibuter::with('autodistributerData')->findOrFail($id);
        return view('autodisributers.show', compact('file'));
    }

    // Delete a file.................................................................................................................................
    public function destroy($id)
    {
        $autoDistributer = AutoDirtibuter::find($id);
        $autoDistributer->delete();
         // Active Log Report...............................
         ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'delete',
            'file_type' => 'Auto Distributer',
            'file_name' => $autoDistributer->file_name,
            'operation_time' => now(),
        ]);
        return back()->with('success', 'File deleted.');
    }

    // Download File.................................................................................................................................
    public function download($id)
    {
        $file = AutoDirtibuter::findOrFail($id);
        $filePath = $file->file_path;

        if (!Storage::disk('public')->exists($filePath)) {
            return redirect()->route('autodistributers.index.index')->with('error', 'File not found.');
        }

          // Active Log Report...............................
          ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'download',
            'file_type' => 'Auto Distributer',
            'file_name' => $file->file_name,
            'operation_time' => now(),
        ]);
        return Storage::disk('public')->download($filePath);
    }


    
}

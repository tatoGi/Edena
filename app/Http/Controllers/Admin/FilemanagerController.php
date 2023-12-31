<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;

class FilemanagerController extends Controller
{
    public function index()
    {
        $sections = Section::where('parent_id', null)->orderBy('order', 'asc')->with('children')->get();

        return view('admin.filemanager.index', compact('sections'));
    }

    public function create($id)
    {
        return view('admin.filemanager.add');
    }
}

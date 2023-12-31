<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuSection;
use App\Models\PostTranslation;
use App\Models\Section;
use App\Models\SectionTranslation;
use App\Models\Slug;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SectionController extends Controller
{
    /**
     * index
     *  Lists Sections
     *
     * @return void
     */
    public function index()
    {
        $sections = Section::where('parent_id', null)->orderBy('order', 'asc')->with('children')->get();
        // dd($sections);

        return view('admin.sections.list', compact('sections'));
    }

    public function create()
    {
        $sectionTypes = sectionTypes();

        $sections = Section::with('translations')->get();

        $menuTypes = menuTypes();
        $organizations_type = DB::table('sections')->where('type_id', 9)->exists();

        return view('admin.sections.add', compact(['sectionTypes', 'sections', 'menuTypes', 'organizations_type']));
    }

    public function store(Request $request)
    {
        $values = $request->all();

        Validator::validate($values, [
            'type_id' => 'required',
        ]);
        if ($request->cover != '') {

            $originalName = $request->cover->getClientOriginalName();
            $newName = uniqid().'.'.$request->cover->getClientOriginalExtension();
            $request->cover->move(config('config.image_path'), $newName);
            $values['cover'] = $newName;

        }

        // if($values['image'] != ''){

        //     $originalName =$values['image']->getClientOriginalName();
        //     $newName = uniqid() . "." . $values['image']->getClientOriginalExtension();
        //     $values['image']->move(config('config.image_path'), $newName );
        //     $values['image'] = $newName;

        // }

        $values['additional'] = getAdditional($values, config('sectionAttr.additional'));

        foreach (config('app.locales') as $locale) {
            // $values[$locale]['slug'] = str_replace(' ', '-', $values[$locale]['title']);
            if ($values[$locale]['slug'] != '') {
                $values[$locale]['slug'] = SlugService::createSlug(SectionTranslation::class, 'slug', $values[$locale]['slug']);
                $values[$locale]['slug'] = SlugService::createSlug(PostTranslation::class, 'slug', $values[$locale]['slug']);
            } else {
                $values[$locale]['slug'] = SlugService::createSlug(SectionTranslation::class, 'slug', $values[$locale]['title']);
            }
            $fullslug[$locale] = $locale.'/'.$values[$locale]['slug'];
            $values[$locale]['locale_additional'] = getAdditional($values[$locale], config('sectionAttr.translateable_additional'));
        }
        $section = Section::create($values);
        if (isset($values['menu_types']) && $values['menu_types'] != '') {
            foreach ($values['menu_types'] as $type) {
                MenuSection::create([
                    'section_id' => $section->id,
                    'menu_type_id' => $type,
                ]);
            }
        }

        foreach (config('app.locales') as $locale) {
            $section->slugs()->create([
                'fullSlug' => $fullslug[$locale],
                'slugable_id' => $section->id,
                'locale' => $locale,
            ]);
        }

        return Redirect::route('section.list', [app()->getLocale()]);
    }

    public function edit($id)
    {
        $sectionTypes = sectionTypes();
        $section = Section::where('id', $id)->with(['translations', 'menuTypes'])->first();
        $sections = Section::with('translations')->where('id', '!=', $section->id)->where('parent_id', '!=', $section->id)->orWhere('parent_id', null)->get();
        $menuTypes = menuTypes();
        // dd($menuTypes);
        return view('admin.sections.edit', compact(['sections', 'section', 'sectionTypes', 'menuTypes']));
    }

    public function update($id, Request $request)
    {

        $values = $request->all();

        Validator::validate($values, [
            'type_id' => 'required',
        ]);
        $section = Section::where('id', $id)->with('translations')->first();

        MenuSection::where('section_id', $id)->delete();

        Slug::where('slugable_id', $id)->delete();

        if ($request->cover != '') {
            $originalName = $request->cover->getClientOriginalName();
            $newName = uniqid().'.'.$request->cover->getClientOriginalExtension();
            $request->cover->move(config('config.image_path'), $newName);
            $values['cover'] = $newName;
        }

        $values['additional'] = getAdditional($values, config('sectionAttr.additional'));

        foreach (config('app.locales') as $locale) {
            if (isset($section[$locale]['slug'])) {

                if ($values[$locale]['slug'] != $section[$locale]->slug) {

                    $values[$locale]['slug'] = SlugService::createSlug(SectionTranslation::class, 'slug', $values[$locale]['slug']);
                }
            }

            $section->slugs()->create([
                'fullSlug' => $locale.'/'.$values[$locale]['slug'],
                'slugable_id' => $id,
                'locale' => $locale,
            ]);

            $values[$locale]['locale_additional'] = getAdditional($values[$locale], config('sectionAttr.translateable_additional'));

        }
        $section = Section::find($id)->update($values);
        // dd($values['menu_types']);
        if (isset($values['menu_types']) && $values['menu_types'] !== null) {
            foreach ($values['menu_types'] as $type) {
                MenuSection::create([
                    'section_id' => $id,
                    'menu_type_id' => $type,
                ]);
            }
        }
        session()->flash(app()->getLocale());
        return  redirect()->back();
    }

    public function destroy($id)
    {
        $sec = Section::find($id)->with('translations')->first();
        foreach (Section::find($id)->slugs()->get() as $slug) {
            Slug::where('fullSlug', 'LIKE', $slug->fullSlug.'%')->delete();
        }

        Section::find($id)->slugs()->delete();
        Section::find($id)->delete();

        return Redirect::route('section.list', [app()->getLocale()]);
    }

    public function arrange(Request $request)
    {
        $array = $request->input('orderArr');
        Section::rearrange($array);

        return ['error' => false];
    }

    public function DeleteCover($que)
    {

        $section = Section::where('id', $que)->first();
        if ($section->cover != '') {
            unlink(config('config.image_path').$section->cover);
        }
        $section->cover = '';
        $User_Update = Section::where('id', $que)->update(['cover' => null]);

        // $file = files::where('id', $que)->first();
        // unlink(public_path("uploads/files/{$file->file}"));
        // files::find($que)->delete();

        return response()->json(['success' => 'File Deleted']);
    }
}

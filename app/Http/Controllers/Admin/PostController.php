<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostFile;
use App\Models\PostTranslation;
use App\Models\Product;
use App\Models\Section;
use App\Models\Slug;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class PostController extends Controller
{
    public function index($sec)
    {
        $section = Section::where('id', $sec)->with('translations')->first();

        if (isset($section->type) && in_array($section->type['type'], [3, 7, 8, 4])) {
            $post = Post::where('section_id', $sec)->with(['translations', 'slugs'])->first();
            if (isset($post) && $post !== null) {
                return Redirect::route('post.edit', [app()->getLocale(), $post->id]);
            }

            return Redirect::route('post.create', [app()->getLocale(), $sec]);

        }

        $posts = Post::where('section_id', $sec)->orderBy('date', 'desc')->orderBy('created_at', 'asc')
            ->join('post_translations', 'posts.id', '=', 'post_translations.post_id')
            ->where('post_translations.locale', '=', app()->getLocale())
            ->select('posts.*', 'post_translations.text', 'post_translations.desc', 'post_translations.title', 'post_translations.locale_additional', 'post_translations.slug');

        $posts = $posts->with(['translations', 'slugs'])->paginate(settings('Paginate'));

        return view('admin.posts.list', compact(['section', 'posts']));
    }

    public function create($sec)
    {
        $section = Section::where('id', $sec)->with('translations')->first();

        return view('admin.posts.add', compact(['section']));
    }

    public function store($sec, Request $request)
    {

        $section = Section::where('id', $sec)->with('translations')->first();

        $values = $request->all();

        $values['section_id'] = $sec;
        $values['author_id'] = auth()->user()->id;
        $postFillable = (new Post)->getFillable();

        $postTransFillable = (new PostTranslation)->getFillable();

        if ($request->icon != '') {

            $newiconName = uniqid().'.'.$request->icon->getClientOriginalExtension();
            $request->icon->move(config('config.icon_path'), $newiconName);
            $values['icon'] = $newiconName;

        }

        if (isset($values['cover']) && ($values['cover'] != '')) {
            $newcoverName = uniqid().'.'.$values['cover']->getClientOriginalExtension();
            $values['cover']->move(config('config.file_path'), $newcoverName);
            $values['cover'] = '';
            $values['cover'] = $newcoverName;
        } elseif (isset($values['old_cover'])) {
            $values['cover'] = $values['old_cover'];
        }

        $values['additional'] = getAdditional($values, array_diff(array_keys($section->fields['nonTrans']), $postFillable));

        foreach (config('app.locales') as $locale) {
            if (isset($values[$locale]['slug']) != '') {
                $values[$locale]['slug'] = SlugService::createSlug(PostTranslation::class, 'slug', $values[$locale]['slug']);
            } elseif (isset($values[$locale]['title'])) {
                $values[$locale]['slug'] = SlugService::createSlug(PostTranslation::class, 'slug', $values[$locale]['title']);
            }

            if (isset($values[$locale]['file']) && $values[$locale]['file'] != '') {
                $newfileName = uniqid().'.'.$values[$locale]['file']->getClientOriginalExtension();
                $orignalName = $values[$locale]['file']->getClientoriginalname();
                $values[$locale]['file']->move(config('config.file_path'), $newfileName);
                $values[$locale]['file'] = '';
                $values[$locale]['file'] = $newfileName;
                $values[$locale]['filename'] = $orignalName;

            }

            $values[$locale]['locale_additional'] = getAdditional($values[$locale], array_diff(array_keys($section->fields['trans']), $postTransFillable));
        }
        $post = Post::create($values);

        foreach (config('app.locales') as $locale) {
            $post->slugs()->create([
                'fullSlug' => $locale.'/'.$post->translate($locale)->slug,
                'locale' => $locale,
            ]);
        }

        if (isset($values['files']) && count($values['files']) > 0) {

            foreach ($values['files'] as $key => $files) {
                foreach ($files['file'] as $k => $file) {
                    $postFile = new PostFile;
                    $postFile->type = $key;
                    $postFile->file = $file;
                    $postFile->title = $values['files'][$key]['desc'][$k];
                    $postFile->post_id = $post->id;
                    $postFile->save();
                }
            }
        }

        return Redirect::route('post.list', [app()->getLocale(), $section->id]);
    }

    public function edit($id)
    {

        $post = Post::where('id', $id)->with(['translations', 'files'])->first();

        $product = Product::where('post_id', $post->id)->get();

        $section = Section::where('id', $post->section_id)->with('translations')->first();

        return view('admin.posts.edit', compact('section', 'product', 'post'));
    }

    public function update($id, Request $request)
    {

        $post = Post::where('id', $id)->with('translations')->first();

        $section = Section::where('id', $post->section_id)->with('translations')->first();

        Slug::where('slugable_id', $id)->delete();

        $values = $request->all();
        // dd($values);
        $postFillable = (new Post)->getFillable();

        $postTransFillable = (new PostTranslation)->getFillable();

        if ($request->icon != '') {

            $newiconName = uniqid().'.'.$request->icon->getClientOriginalExtension();
            $request->icon->move(config('config.icon_path'), $newiconName);
            $values['icon'] = $newiconName;

        }

        if (isset($values['cover']) && ($values['cover'] != '')) {

            $newcoverName = uniqid().'.'.$values['cover']->getClientOriginalExtension();
            $values['cover']->move(config('config.file_path'), $newcoverName);
            $values['cover'] = '';
            $values['cover'] = $newcoverName;
        } elseif (isset($values['old_cover'])) {
            $values['cover'] = $values['old_cover'];
        }
        $values['additional'] = getAdditional($values, array_diff(array_keys($section->fields['nonTrans']), $postFillable));

        foreach (config('app.locales') as $locale) {

            if (isset($values[$locale]['slug'])) {
                $values[$locale]['slug'] = str_replace(' ', '', $values[$locale]['slug']);
            } elseif (isset($values[$locale]['title'])) {
                $values[$locale]['slug'] = str_replace(' ', '', $values[$locale]['title']);
            }

            if (isset($post->slug) && isset($values[$locale]['slug'])) {
                $post->slugs()->create([
                    'fullSlug' => $locale.'/'.$values[$locale]['slug'],
                    'slugable_id' => $id,
                    'locale' => $locale,
                ]);

            }
            if (isset($values[$locale]['file']) && ($values[$locale]['file'] != '')) {

                $newfileName = uniqid().'.'.$values[$locale]['file']->getClientOriginalExtension();
                $values[$locale]['file']->move(config('config.file_path'), $newfileName);
                $values[$locale]['file'] = '';
                $values[$locale]['file'] = $newfileName;
            } elseif (isset($values[$locale]['old_file'])) {
                $values[$locale]['file'] = $values[$locale]['old_file'];
            }
            $values[$locale]['locale_additional'] = getAdditional($values[$locale], array_diff(array_keys($section->fields['trans']), $postTransFillable));

        }
        $allOldFiles = PostFile::where('post_id', $post->id)->get();

        foreach ($allOldFiles as $key => $fil) {
            if (isset($values['old_file']) && count($values['old_file']) > 0) {
                if (! in_array($fil->id, array_keys($values['old_file']))) {
                    $fil->delete();
                }
            } else {
                $fil->delete();
            }
        }

        if (isset($values['files']) && count($values['files']) > 0) {

            foreach ($values['files'] as $key => $files) {
                foreach ($files['file'] as $k => $file) {
                    $postFile = new PostFile;
                    $postFile->type = $key;
                    $postFile->file = $file;
                    $postFile->title = $values['files'][$key]['desc'][$k];
                    $postFile->post_id = $post->id;
                    $postFile->save();
                }
            }
        }

        Post::find($post->id)->update($values);
        
        return Redirect::route('post.update', [app()->getLocale(), $post->id]);
        }

    public function destroy($id)
    {

        $post = Post::where('id', $id)->first();
        // foreach (Post::find($id)->slugs()->get() as $slug) {

        //     // Post::find($id)->delete();
        // }
        $section = Section::where('id', $post->section_id)->with('translations')->first();

        $files = PostFile::where('post_id', $post->id)->get();
        foreach ($files as $file) {

            if (file_exists(config('config.image_path').$file->file)) {
                unlink(config('config.image_path').$file->file);
            } else {
                dd('File does not exists.');
            }
            if (file_exists(config('config.image_path').'thumb/'.$file->file)) {
                unlink(config('config.image_path').'thumb/'.$file->file);
            } else {
                dd('File does not exists.');
            }
            $file->delete();
        }

        Post::find($id)->slugs()->delete();

        PostTranslation::where('post_id', $post->id)->delete();

        $post->delete();

        return Redirect::route('post.list', [app()->getLocale(), $section->id]);
    }

    public function DeleteFile($que)
    {
        $file = PostTranslation::where('post_id', $que)->first();

        if (file_exists(config('config.file_path').$file->file)) {
            unlink(config('config.file_path').$file->file);

        } else {
            dd('File does not exists.');

        }

        if ($file->file != '') {
            $file->file->delete();
            $file->file = '';
        }

        return response()->json(['success' => 'File Deleted']);
    }
}

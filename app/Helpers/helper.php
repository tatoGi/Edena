<?php

use App\Models\Directory;
use App\Models\Post;
use App\Models\Section;
use App\Models\Slug;
use Illuminate\Support\Facades\Config;

function settings($key = null, array $replace = [])
{
    if ($key == null) {
        return collect(config('settings'));
    }
    $settings = collect(config('settings'))->mapWithKeys(function ($a) {
        return $a;
    });
    $return = isset($settings[$key]) ? is_array($settings[$key]['value']) ? $settings[$key]['value'][app()->getLocale()] : $settings[$key]['value'] : null;

    if (count($replace)) {
        return replace($replace, $return);
    } else {
        return $return;
    }

}

function arrayToPhpArray($arr)
{
    return "<?php \n\nreturn ".arrayToString($arr).';';
}

function arrayToString($arr, $tabs = 0, $startTabs = true)
{
    return rtrim(arrayToStringWrapper($arr, $tabs, $startTabs), ",\n ");
}

function arrayToStringWrapper($arr, $tabs = 0, $startTabs = true)
{
    $result = ($startTabs ? str_repeat("\t", $tabs) : '').'['."\n";
    if ($startTabs) {
        $tabs++;
    }
    foreach ($arr as $key => $value) {
        $result .= str_repeat("\t", $tabs).json_encode($key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).' => ';
        if (is_array($value)) {
            $result .= arrayToStringWrapper($value, $tabs + 1, false);
        } else {
            $result .= json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).",\n";
        }
    }
    $tabs--;
    $result .= str_repeat("\t", $tabs).'],'."\n";

    return $result;
}

/**
 * sectionTypes
 *  get all section types that are in config/sectionTypes folder.  keys are names
 *
 * @return void
 */
function sectionTypes()
{

    return collect(Config::get('sectionTypes'))->sortBy(function ($value, $key) {
        return $value['id'];
    });
}

/**
 * bannerTypes
 *  get all section types that are in config/sectionTypes folder.  keys are names
 *
 * @return void
 */
function bannerTypes()
{
    return Config::get('bannerTypes');
}
function bannerTypesOrdered()
{
    return collect(Config::get('bannerTypes'))->sortBy(function ($value, $key) {
        return $value['id'];
    });
}

/**
 * bannerTypes
 *  get all section types that are in config/sectionTypes folder.  keys are names
 *
 * @return void
 */
function directoryTypes()
{
    return Config::get('directoryTypes');
}

/**
 * menuTypes
 *  get menu types frpm config/menuTypes.php
 *
 * @return void
 */
function menuTypes()
{
    return Config::get('menuTypes');
}

function isMenuType($query, $type)
{
    return array_search(array_search($type, menuTypes()), $query->menuTypes->pluck('menu_type_id')->toArray()) !== false;
}

function menuTypeByKey($val)
{
    return array_keys(menuTypes(), $val)[0];
}

/**
 * locales
 *  gets locales from config/app.php locales array
 *
 * @return void
 */
function locales()
{
    return config('app.locales');
}

function getSectionsWithTypes($options)
{
    $secTypeIds = [];
    if (! empty($options)) {
        foreach ($options as $opt) {

            if (isset($opt)) {
                $secTypeIds[] = Config::get('sectionTypes')[$opt]['id'];
            }
        }
        $sections = Section::whereIn('type_id', array_values($secTypeIds))->with('translations')->get();

        return $sections;
    }

}

/**
 * getAdditional
 *  get additional attributes as array
 *
 * @param  mixed  $arr (request ass array)
 * @param  mixed  $additionalList (list of fillable additionals)
 * @return void
 */
function getAdditional($arr, $additionalList)
{
    $additional = [];
    foreach ($arr as $key => $item) {
        if (in_array($key, $additionalList)) {
            $additional[$key] = $item;
        }
    }

    return $additional;
}

// function genFullSlug($item, $locale){
//   if ($item->parent_id) {
//     $parent = Section::find($item->parent_id);
//     $fullSlug = $parent->slugs()->where('locale', $locale)->first()->fullSlug.'/'.$item->slug;
//   }else{

//     $fullSlug = '/'.$locale.'/'.$item->slug;
//   }
//   return $fullSlug;
// }

function genFullSlug($item, $locale)
{
    if ($item->parent()->first() !== null) {
        $parent = $item->parent()->first();

        $fullSlug = Slug::where('slugable_type', get_class($parent))->where('slugable_id', $parent->id)->where('locale', $locale)->first()->fullSlug.'/'.$item->translate($locale)->slug;
    } else {

        $fullSlug = '/'.$locale.'/'.$item->slug;
    }

    return $fullSlug;
}

function genValidation($fields)
{
    $validate = [];

    foreach ($fields as $key => $field) {
        if (isset($field['validation'])) {
            $validate[$key] = $field['validation'];

        }
    }

    return $validate;
}

function str_limit($str, $char = 100)
{
    return \Illuminate\Support\Str::limit($str, $char);
}

function thumb($thumb)
{
    return '/'.config('config.image_path').config('config.thumb_path').$thumb;
}
function image($thumb)
{
    return '/'.config('config.image_path').$thumb;
}

function isArr($item)
{
    return is_array($item['value'] ?? null);
}

function settingTransAttrs($setting)
{
    return collect($setting)->filter(
        function ($item) {
            return is_array($item['value']);
        }
    );
}
function settingNonTransAttrs($setting)
{
    return collect($setting)->filter(
        function ($item) {
            return ! is_array($item['value']);
        }
    );
}

function directories($type)
{
    $type = array_search($type, config('directoryTypes'));

    return \App\Models\Directory::where('type_id', $type)->doesntHave('children')->with('translations')->get();

}

function getVideoImage($url)
{
    if ($url !== null) {
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);

        return 'https://img.youtube.com/vi/'.$match[1].'/0.jpg';
    }
}
function getMunicipality()
{
    $section = Section::where('type_id', 14)->with('translations')->first();
    $posts = Post::where('section_id', $section->id)->with('translation')->get();

    return view('admin.form-controllers.nonTrans.country', compact('section', 'posts'));
}

function getService()
{
    $section = Section::where('type_id', 6)->with('translations')->first();
    $posts = Post::where('section_id', $section->id)->with('translation')->get();

    return view('admin.form-controllers.nonTrans.country', compact('section', 'posts'));
}
function getOrganizations()
{
    $organizations = Section::where('type_id', 9)->with('translations', 'posts')->first();
    $organizations_posts = Post::where('section_id', $organizations->id)->with('translation')->get();

    return view('admin.form-controllers.nonTrans.organizations', compact('organizations', 'organizations_posts'));
}
function getCategory()
{
    $category = Directory::where('type_id', 0)->with('translation')->get();

    return view('admin.form-controllers.nonTrans.category', compact('category'));
}

function cutLocaleString($str)
{
    $needtohide = '[:en]' ?? '[enter]';

    return str_replace("$needtohide", '', $str);
}


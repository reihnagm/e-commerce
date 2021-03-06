<?php

namespace App\Http\Controllers;

use Toastr;
use File;
use Storage;

use Intervention\Image\Constraint;
use Intervention\Image\Facades\Image;

use TCG\Voyager\Facades\Voyager;

use Illuminate\Support\Facades\Input;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

use App\Mail\BlogPublished;
use App\Http\Requests\BlogRequest;
use App\Http\Resources\BlogResource as BlogCollection;
use App\Models\User;
use App\Models\Blog;
use App\Models\BlogComment;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Category;
use App\Models\Notification;

class BlogController extends Controller
{
    public function create(Request $request)
    {
        return view('blog/create', ['user' => $request->user(), 'tags' => Tag::all()]);
    }

    public function store(BlogRequest $request)
    {

        // ALTERNATIVE STORE IMAGE BLOB 1
        // if ($request->hasFile('img')) {
        //      $img = $request->file('img');
        //      $filename = time(). "-" . $img->getClientOriginalName();
        //      $image = base64_encode(file_get_contents($request->file('img')));
        //      $blog->img = $image;
        //   }

        // ALTERNATIVE STORE IMAGE BLOB 2
        // if ($request->hasFile('img')) {
        //     $file =Input::file('img');
        //     $imagedata = file_get_contents($file);
        //     $base64 = 'data:image/jpeg;base64,'. base64_encode($imagedata);
        //     $blog->img = $base64;
        // }

        $slug = str_slug($request->title, '-');

        $blog_slug = Blog::where('slug', $slug)->first();

        if ($blog_slug != null) {
            $slug = $slug . '-' .time();
        }

        // COPY FROM VOYAGER UPLOAD IMAGE
        $fullFilename = null;
        $resizeWidth = 1800;
        $resizeHeight = null;

        $file = $request->file('img');

        $path =  '/'.date('F').date('Y').'/';

        $filename = basename($file->getClientOriginalName().'-'.time(), '.'.$file->getClientOriginalExtension());

        $fullPath = 'blogs'.$path.$filename.'.'.$file->getClientOriginalExtension();

        $image = Image::make($file)->resize($resizeWidth, $resizeHeight, function (Constraint $constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->encode($file->getClientOriginalExtension(), 75);

        Storage::disk('public')->put($fullPath, (string) $image, 'public');

        $fullFilename = $fullPath;

        if ($request->has('publish')) {
            $blog = Blog::create([
         "title"   => $request->title,
         "img"     => $fullFilename,
         "caption" => $request->caption,
         "desc"    => $request->desc,
         "user_id" => auth()->user()->id,
         "slug"    => $slug
       ]);

            $blog->tags()->attach($request->tags);

            $blog->save();

            auth()->user()->blogs()->save($blog);

            Toastr::info('Successfully Created a Blog!');
        } else {
            $this->saveToDraft($request, $slug, $fullFilename);
        }

        return redirect(route('user.profile'));
    }

    public function show($id)
    {
        $blog = Blog::with(['user', 'tags:name', 'comments.user'])->where('slug', $id)->first();

        $blogs = Blog::with(['user', 'tags:name'])->paginate(3, ['*'], 'blog-page');

        $products = Product::with(['user','category:name'])->paginate(3, ['*'], 'product-page');

        $tags = Tag::all();

        if ($blog) {
            $comments = $blog->comments()->with(['user','likes','unlikes'])->paginate(6, ['*'], 'comment-page');
        } else {
            $comments = null;
        }

        return view('blog.show', ['user' => request()->user(), 'blog' => $blog, 'blogs' => $blogs, 'comments' => $comments, 'products' => $products, 'tags' => $tags]);
    }


    public function edit($id, Request $request)
    {
        return view('blog/edit', ['user' => $request->user(),'blog' => Blog::where('slug', $id)->first(), 'tags' => Tag::all()]);
    }

    public function update(BlogRequest $request, $id)
    {
        $blog = Blog::findOrFail($id);

        // REMOVED FILE EXISTS WHEN DELETE ACTION
        // AND GETTING NEW FILE IMAGE

        $blog_img = Blog::findOrFail($id);

        $oldImg = public_path("storage/{$blog_img->img}");

        if (File::exists($oldImg)) {
            unlink($oldImg);
        }

        // COPY FROM VOYAGER UPLOAD IMAGE
        $fullFilename = null;
        $resizeWidth = 1800;
        $resizeHeight = null;

        $file = $request->file('img');

        $path =  '/'.date('F').date('Y').'/';

        $filename = basename($file->getClientOriginalName().'-'.time());

        $fullPath = 'blogs'.$path.$filename.'.'.$file->getClientOriginalExtension();

        $image = Image::make($file)->resize($resizeWidth, $resizeHeight, function (Constraint $constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->encode($file->getClientOriginalExtension(), 75);

        Storage::disk('public')->put($fullPath, (string) $image, 'public');

        $fullFilename = $fullPath;

        $slug = str_slug($request->title, '-');

        $blog_slug = Blog::where('slug', $slug)->first();

        if ($blog_slug != null) {
            $slug = $slug . '-' .time();
        }

        if ($request->has('edit')) {
            $blog->update([
        "title"  => $request->title,
        "slug" => $slug,
        "img"   => $fullFilename,
        "caption" => $request->caption,
        "desc"  => $request->desc,
        "user_id" => auth()->user()->id
      ]);

            // DEFAULT STORE IMAGE
            // if ($request->hasFile('img')) {
            //
            //     $img = $request->file('img');
            //
            //     $filename = time(). "-" . $img->getClientOriginalName();
            //
            //     $request->img->storeAs('public/blogs/images', $filename);
            //
            //     $blog->img = $filename;
            //
            // }

            $blog->tags()->sync($request->tags);

            auth()->user()->blogs()->save($blog);

            Toastr::info('Successfully Updated Blog!');
        } else {
            $this->updateDraft($blog, $request, $slug, $fullFilename);
        }

        return redirect('/profile');
    }

    public function destroy($id)
    {
        $blog = Blog::findOrFail($id);

        // REMOVED FILE EXISTS WHEN DELETE ACTION
        // AND GETTING NEW FILE IMAGE

        $blog_img = public_path("storage/{$blog->img}");

        // IF NOT USE MEDIA UPLOAD FROM PACKAGE VOYAGER UNCOMMENT
        // $blogImg = public_path("storage/blogs/images/{$blog->img}");

        if (File::exists($blog_img)) {
            unlink($blog_img);
        }

        $blog->tags()->detach();

        $blog->delete();

        Toastr::info('Successfully Deleted Blog!');

        return back();
    }

    public function saveToDraft($requestDraftData, $slug, $fullFilename)
    {
        $blog = Blog::create([
          "title" => $requestDraftData->title,
          "slug"  => $slug,
          "img" => $fullFilename,
          "caption" => $requestDraftData->caption,
          "desc" => $requestDraftData->desc,
          "draft" => 1,
          "user_id" => auth()->user()->id
        ]);

        $blog->tags()->attach($requestDraftData->tags);

        $blog->save();

        Toastr::info('Successfully Save to Draft!');
    }

    public function updateDraft($blog, $requestDraftData, $slug, $fullFilename)
    {
        $blog->update([
        "title" => $requestDraftData->title,
        "slug"  => $slug,
        "img" => $fullFilename,
        "caption" => $requestDraftData->caption,
        "desc" => $requestDraftData->desc,
        "draft" => 1,
        "user_id" => auth()->user()->id
      ]);

        $blog->tags()->sync($requestDraftData->tags);

        auth()->user()->blogs()->save($blog);

        Toastr::info('Successfully Updated Draft!');
    }

    public function draft()
    {
        return view("blog.draft", ["blogs" => Blog::where("user_id", auth()->user()->id)->where("draft", 1)->paginate(5)]);
    }

    public function publish()
    {
        $blog = Blog::where("draft", 1)->update([
         "draft" => 0
       ]);

        Toastr::info('Successfully Published Blog!');

        return back();
    }
}

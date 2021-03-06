<?php

namespace App\Http\Controllers\Voyager;

use File;
use Storage;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;

use Intervention\Image\Constraint;
use Intervention\Image\Facades\Image;

use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Events\BreadDataUpdated;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;
use TCG\Voyager\Http\Controllers\Traits\BreadRelationshipParser;

class UserController extends VoyagerBaseController
{
    use BreadRelationshipParser;

    public function update(Request $request, $id)
    {

        $this->validate($request, [
          'name' => "required",
          'email' => "required|unique:users,email, $id",
          'password' => "regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-_]).{8,}$/"
        ]);

        $user = \App\Models\User::findOrFail($id);

        $user_img = \App\Models\User::findOrFail($id);

        $oldImg = public_path("storage/{$user_img->avatar}");

        // REMOVED FILE EXISTS WHEN DELETE ACTION
        // AND GETTING NEW FILE IMAGE
        if (File::exists($oldImg)) {
            unlink($oldImg);
        }

        // COPY FROM VOYAGER UPLOAD IMAGE
        $fullFilename = null;
        $resizeWidth = 1800;
        $resizeHeight = null;

        $file = $request->file('avatar');

        $path =  '/'.date('F').date('Y').'/';

        $filename = basename(str_random());

        $fullPath = 'users'.$path.$filename.'.'.$file->getClientOriginalExtension();

        $image = Image::make($file)->resize($resizeWidth, $resizeHeight, function (Constraint $constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->encode($file->getClientOriginalExtension(), 75);

        Storage::disk('public')->put($fullPath, (string) $image, 'public');

        $fullFilename = $fullPath;

        $user->update([
        "name" =>$request->name,
        "email" =>$request->email,
        "avatar" => $fullFilename,
        "locale" => $request->locale
      ]);

      $user->roles()->sync($request->roles);

      return redirect(route('voyager.users.index'));

    }

    public function store(Request $request) {

      $this->validate($request,[
        'name' => "required",
        'email' => "required|unique:users,email",
        'password' => "regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-_]).{8,}$/"
      ]);

      $slug = str_slug($request->name, '-');

      $user_slug =  \App\Models\User::where('slug', $slug)->first();

      if ($user_slug != null) {
          $slug = $slug . '-' .time();
      }

      // COPY FROM VOYAGER UPLOAD IMAGE
      $fullFilename = null;
      $resizeWidth = 1800;
      $resizeHeight = null;

      $file = $request->file('avatar');

      $path =  '/'.date('F').date('Y').'/';

      $filename = basename($file->getClientOriginalName().'-'.time(), '.'.$file->getClientOriginalExtension());

      $fullPath = 'users'.$path.$filename.'.'.$file->getClientOriginalExtension();

      $image = Image::make($file)->resize($resizeWidth, $resizeHeight, function (Constraint $constraint) {
          $constraint->aspectRatio();
          $constraint->upsize();
      })->encode($file->getClientOriginalExtension(), 75);

      Storage::disk('public')->put($fullPath, (string) $image, 'public');

       $fullFilename = $fullPath;

       $user = \App\Models\User::create([
         'name' => $slug,
         'avatar' => $fullFilename,
         'slug' => str_slug($request->name,'-'),
         'email' => $request->email,
         'password' => Hash::make($request->password),
         'locale' => $request->locale
       ]);

       $user->roles()->attach($request->roles);

       $user->save();

       return redirect(route('voyager.users.index'));
    }


}

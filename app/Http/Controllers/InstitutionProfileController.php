<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInstitutionProfileRequest;
use App\Models\InstitutionProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InstitutionProfileController extends Controller
{
    /**
     * Show the institution profile update form.
     */
    public function edit(): View
    {
        return view('institution.edit', [
            'profile' => InstitutionProfile::current(),
        ]);
    }

    /**
     * Update the institution profile.
     */
    public function update(UpdateInstitutionProfileRequest $request): RedirectResponse
    {
        $profile = InstitutionProfile::current();

        $data = $request->safe()->only([
            'government_name',
            'institution_name',
            'address',
            'website',
            'email',
        ]);

        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $profile->update($data);

        return redirect()
            ->route('profil-instansi.edit')
            ->with('status', 'Profil instansi berhasil diperbarui.');
    }
}

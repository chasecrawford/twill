<?php

namespace A17\Twill\Http\Controllers\Admin;

use A17\Twill\Repositories\SettingRepository;

class SettingController extends Controller
{
    /**
     * @param SettingRepository $settings
     */
    public function __construct(SettingRepository $settings)
    {
        parent::__construct();
        $this->settings = $settings;
    }

    /**
     * @param string $section
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function index($section)
    {
        return view()->exists('admin.settings.' . $section) ? view('admin.settings.' . $section, [
            'customForm' => true,
            'editableTitle' => false,
            'customTitle' => ucfirst($section) . ' settings',
            'section' => $section,
            'form_fields' => $this->settings->getFormFields($section),
            'saveUrl' => route('admin.settings.update', $section),
            'translate' => true,
        ]) : redirect()->back();
    }

    /**
     * @param string $section
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update($section)
    {
        if (array_key_exists('cancel', request()->all())) {
            return redirect()->back();
        }

        $this->settings->saveAll(request()->except('_token'), $section);

        fireCmsEvent('cms-settings.saved');

        return redirect()->back();
    }
}

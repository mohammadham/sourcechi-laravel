<?php

namespace Marvel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvertisementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Check if this is an update request (has ID in route)
        $isUpdate = $this->route('id') !== null;
        
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['image', 'video', 'html'])],
            'position' => ['required', Rule::in([
                'header',
                'sidebar',
                'footer',
                'between_products',
                'product_detail',
                'popup'
            ])],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'open_in_new_tab' => ['boolean'],
            'is_active' => ['boolean'],
            'order' => ['integer', 'min:0'],
            'display_settings' => ['nullable', 'json'],
        ];

        // Type-specific validation for media
        $type = $this->input('type');
        
        if ($type === 'image' || $type === 'video') {
            // Accept either media (file) or media_url (string)
            // For create: at least one is required
            // For update: both are optional
            
            if ($isUpdate) {
                $rules['media'] = ['nullable', 'file'];
                $rules['media_url'] = ['nullable', 'string', 'max:2048'];
            } else {
                // For create, require media OR media_url
                $rules['media'] = ['nullable', 'file'];
                $rules['media_url'] = ['nullable', 'string', 'max:2048'];
            }
            
            // Add type-specific rules for media file
            if ($this->hasFile('media')) {
                if ($type === 'image') {
                    $rules['media'][] = 'mimes:jpg,jpeg,png,gif,webp';
                    $rules['media'][] = 'max:5120'; // 5MB
                } else {
                    $rules['media'][] = 'mimes:mp4,webm,ogg';
                    $rules['media'][] = 'max:51200'; // 50MB
                }
            }
        }

        if ($type === 'html') {
            $rules['html_code'] = ['required', 'string'];
        }

        return $rules;
    }
    
    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $isUpdate = $this->route('id') !== null;
            
            // For create with image/video type, ensure either media or media_url is provided
            if (!$isUpdate && ($type === 'image' || $type === 'video')) {
                if (!$this->hasFile('media') && !$this->input('media_url')) {
                    $validator->errors()->add('media', 'انتخاب فایل یا URL الزامی است');
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'title.required' => 'عنوان تبلیغ الزامی است',
            'type.required' => 'نوع تبلیغ الزامی است',
            'type.in' => 'نوع تبلیغ معتبر نیست',
            'position.required' => 'موقعیت نمایش الزامی است',
            'position.in' => 'موقعیت نمایش معتبر نیست',
            'target_url.url' => 'آدرس URL معتبر نیست',
            'media.required' => 'انتخاب فایل الزامی است',
            'media.file' => 'فایل انتخاب شده معتبر نیست',
            'media.mimes' => 'فرمت فایل پشتیبانی نمی‌شود',
            'media.max' => 'حجم فایل بیش از حد مجاز است',
            'html_code.required' => 'کد HTML الزامی است',
        ];
    }
}

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

        // Type-specific validation
        if ($this->input('type') === 'image' || $this->input('type') === 'video') {
            $rules['media'] = ['nullable', 'file'];
            if ($this->input('type') === 'image') {
                $rules['media'][] = 'mimes:jpg,jpeg,png,gif,webp';
                $rules['media'][] = 'max:5120'; // 5MB
            } else {
                $rules['media'][] = 'mimes:mp4,webm,ogg';
                $rules['media'][] = 'max:51200'; // 50MB
            }
        }

        if ($this->input('type') === 'html') {
            $rules['html_code'] = ['required', 'string'];
        }

        return $rules;
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
            'media.mimes' => 'فرمت فایل پشتیبانی نمی‌شود',
            'media.max' => 'حجم فایل بیش از حد مجاز است',
            'html_code.required' => 'کد HTML الزامی است',
        ];
    }
}

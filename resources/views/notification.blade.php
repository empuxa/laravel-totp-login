<x-mail::message>
# {{ $subject }}

{{ $greeting }}

{!! $introLines[0] !!}

<div style="text-align:center;width:100%;margin:50px 0">
    @foreach($pin as $digit)
        <strong style="border:1px solid blue;padding:15px;font-size:20px;border-radius:5px;margin:0 2px">{{ $digit }}</strong>
    @endforeach
</div>

{{--@component('mail::button', ['url' => $actionUrl, 'color' => 'primary'])--}}
{{--{{ $actionText }}--}}
{{--@endcomponent--}}

{!! $introLines[1] !!}

{!! $introLines[2] !!}

{!! __('notification.email.salutation', ['app' => config('app.name')], $notifiable->locale) !!}

</x-mail::message>

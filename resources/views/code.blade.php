<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- (Force latest IE rendering engine: bit.ly/1c8EiC9 --}}
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="content-language" content="{{ app()->getLocale() }}">

    <title>Enter your code</title>

    <link rel="stylesheet" href="{{ asset('vendor/totp-login/login.css') }}">
    <script defer src="{{ asset('vendor/totp-login/login.js') }}"></script>
</head>
<body class="antialiased">
<div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8 mx-5 space-y-6 sm:space-y-10">
    <div class="sm:mx-auto sm:w-full sm:max-w-lg space-y-3">
        <h1 class="text-center text-2xl md:text-3xl 2xl:text-4xl font-bold 2xl:leading-tight break-words whitespace-normal font-semibold text-gray-900 dark:text-white">
            Enter your code
        </h1>
        <p class="text-sm text-center text-gray-600 dark:text-gray-200 max-w">
            {{ session('message', __('totp-login::controller.handle_identifier_request.success')) }}
        </p>

        <form action="{{ route('totp-login.code.handle') }}" method="POST" x-data="code({{ (int) config('totp-login.code.length') }})" @paste="paste($event)">
            @csrf

            <div class="space-y-6" role="region" aria-label="Enter code">
                @error ('code')
                    <div class="rounded-md bg-red-50 dark:bg-red-400 p-4 text-sm">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-red-400 dark:text-white" xmlns="http://www.w3.org/2000/svg"
                                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                          d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                          clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="font-medium text-red-800 dark:text-white">
                                    {{ $message }}
                                </h3>
                            </div>
                        </div>
                    </div>
                @enderror

                {{-- Code inputs --}}
                <div class="flex justify-center" >
                    <template x-for="(l,i) in length" :key="`code_field_${i}`">
                        <input :id="`code_field_${i}`"
                               :autofocus="i === 0"
                               :aria-label="`Code Element ${i + 1}`"
                               class="h-16 lg:h-20 min-w-0 w-10 sm:w-12 lg:w-16 border border-gray-300 dark:border-gray-600 mx-1 rounded-md flex items-center text-center text-3xl lg:text-4xl text-gray-900 bg-transparent dark:text-gray-200 uppercase focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               value=""
                               name="code[]"
                               maxlength="1"
                               inputmode="numeric"
                               @input="stepForward(i)"
                               @keydown.backspace.prevent="stepBack(i)"
                               @focus="resetValue(i)"
                               required
                        >
                    </template>
                </div>

                {{-- Remember me --}}
                <div class="flex items-center justify-center">
                    <div class="flex items-center" x-data="{ remember: true }">
                        <button type="button"
                                role="switch"
                                x-ref="switch"
                                aria-labelledby="remember"
                                :aria-checked="remember.toString()"
                                :value="remember.toString()"
                                :class="{ 'bg-gray-200 dark:bg-gray-700': !remember, 'bg-green-500': remember }"
                                class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200"
                                @click="remember = !remember"
                        >
                            <span class="sr-only">
                                Remember me
                            </span>
                            <span aria-hidden="true"
                                  :class="{ 'translate-x-0': !remember, 'translate-x-5': remember }"
                                  class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 translate-x-0">
                            </span>
                        </button>

                        <span class="ml-5 flex-grow flex flex-col" id="remember"
                              @click="remember = !remember; $refs.switch.focus()">
                            <span class="font-medium text-default">
                                Remember me
                            </span>
                        </span>
                        <input type="hidden" value="false" name="remember" :value="remember"/>
                    </div>
                </div>

                <div>
                    <button type="submit" id="submit" class="w-full flex inline-flex items-center justify-center px-2.5 md:px-5 py-2.5 font-semibold rounded-md text-white bg-green-500 hover:bg-green-400 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer focus:outline-2 focus:outline-offset-2 h-12 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        Login
                    </button>
                </div>
            </div>
        </form>

        <div class="text-sm flex mt-10 justify-center">
            <form method="POST" action="{{ route('totp-login.identifier.handle') }}">
                @csrf
                <input type="submit" value="Resend the code"
                       class="bg-transparent cursor-pointer text-light dark:text-gray-200 hover:underline">
                <input type="hidden" name="{{ config('totp-login.columns.identifier') }}" value="{{ ${ config('totp-login.columns.identifier') } }}">
            </form>
        </div>



    </div>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- (Force latest IE rendering engine: bit.ly/1c8EiC9 --}}
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="content-language" content="{{ app()->getLocale() }}">

    <title>Enter your code</title>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        const totp_code_length = {{ config('totp-login.code.length') }};
    </script>
</head>
<body class="antialiased">
<div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8 mx-5 space-y-6 sm:space-y-10">
    <div class="sm:mx-auto sm:w-full sm:max-w-lg space-y-3">
        <h1 class="text-center text-2xl md:text-3xl 2xl:text-4xl font-bold 2xl:leading-tight break-words whitespace-normal font-semibold text-gray-900 dark:text-white">
            Enter your code
        </h1>
        <p class="text-sm text-center text-gray-600 dark:text-gray-200 max-w">
            Blabla …
        </p>

        <form action="{{ route('totp-login.code.handle') }}" method="POST">
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
                <div class="flex justify-center" x-data="code()">
                    <template x-for="(l,i) in totp_code_length" :key="`code_field_${i}`">
                        <input :id="`code_field_${i}`"
                               :autofocus="i === 0"
                               :aria-label="`Code Element ${i + 1}`"
                               class="h-16 lg:h-20 w-12 lg:w-16 border border-gray-300 dark:border-gray-600 mx-1 rounded-md flex items-center text-center text-3xl lg:text-4xl text-gray-900 bg-transparent dark:text-gray-200 uppercase focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               value=""
                               name="code[]"
                               maxlength="1"
                               inputmode="numeric"
                               @keyup="stepForward(i)"
                               @keydown.backspace="stepBack(i)"
                               @focus="resetValue(i)"
                               autofocus
                               required
                        >
                    </template>
                </div>

                {{-- Remember me --}}
                <div class="flex items-center justify-center">
                    <div class="flex items-center" x-data="{ remember: true }">
                        <button type="button"
                                role="switch"
                                aria-labelledby="remember"
                                :aria-checked="remember.toString()"
                                :value="remember.toString()"
                                :class="{ 'bg-gray-200 dark:bg-gray-700': !remember, 'bg-green-500': remember }"
                                class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200"
                                @click="remember = !remember"
                        >
                            <span class="sr-only">
                                {{ __('views/auth.totp.remember', ['days' => 30]) }}
                            </span>
                            <span aria-hidden="true"
                                  :class="{ 'translate-x-0': !remember, 'translate-x-5': remember }"
                                  class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 translate-x-0">
                            </span>
                        </button>

                        <span class="ml-5 flex-grow flex flex-col" id="remember"
                              @click="remember = !remember; $refs.switch.focus()">
                            <span class="font-medium text-default">
                                Info text regarding remember me…
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

        <script>
            window.addEventListener('load', function () {
                const paste = document.querySelector('body');

                paste.addEventListener('paste', (event) => {
                    event.preventDefault();

                    let paste = (event.clipboardData || window.clipboardData).getData('text');

                    paste = paste.replace(/\s+/g, '');

                    if (isNaN(parseInt(paste)) || paste.length !== totp_code_length) {
                        return;
                    }

                    for (let i = 0; i < paste.length; i++) {
                        document.getElementById(`code_field_${i}`).value = paste[i];
                    }

                    document.getElementById(`submit`).focus();
                    document.getElementById(`submit`).click();
                });
            });

            function code() {
                return {
                    resetValue(i) {
                        for (let x = 0; x < totp_code_length; x++) {
                            if (x >= i) document.getElementById(`code_field_${x}`).value = '';
                        }
                    },

                    stepForward(i) {
                        // Last input has been filled; there is no next input
                        if (document.getElementById(`code_field_${i}`).value && i === totp_code_length - 1) {
                            document.getElementById(`submit`).focus();
                            return;
                        }

                        // Return if the next input is already filled (conflict with paste)
                        if (document.getElementById(`code_field_${i + 1}`).value) {
                            return;
                        }

                        // Next input is empty
                        if (document.getElementById(`code_field_${i}`).value && i !== totp_code_length - 1) {
                            document.getElementById(`code_field_${i + 1}`).focus();
                            document.getElementById(`code_field_${i + 1}`).value = '';
                        }
                    },

                    stepBack(i) {
                        if (i === 0) {
                            return;
                        }

                        document.getElementById(`code_field_${i - 1}`).focus();
                        document.getElementById(`code_field_${i - 1}`).value = '';
                    }
                }
            }
        </script>

    </div>
</div>
</body>
</html>

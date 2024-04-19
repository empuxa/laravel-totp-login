<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- (Force latest IE rendering engine: bit.ly/1c8EiC9 --}}
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="content-language" content="{{ app()->getLocale() }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <title>Enter your login</title>
</head>
<body class="antialiased">
<div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8 mx-5 space-y-6 sm:space-y-10">
    <div class="sm:mx-auto sm:w-full sm:max-w-lg space-y-3">
        <h1 class="text-center text-2xl md:text-3xl 2xl:text-4xl font-bold 2xl:leading-tight break-words whitespace-normal font-semibold text-gray-900 dark:text-white">
            Enter your login
        </h1>

        {{-- You only need the form parts starting here --}}
        <form action="{{ route('totp-login.identifier.handle') }}" method="POST">
            @csrf

            <div class="space-y-6">
                @error ('email')
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

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <p>
                        Enter some information about the session length or whatever you want.
                    </p>
                </div>

                <div class="space-y-6" role="region" aria-label="Request PIN">
                    <div>
                        <label for="{{ config('totp-login.columns.identifier') }}" class="block font-semibold text-gray-700 dark:text-gray-200">
                            Email address
                        </label>
                        <div class="mt-1 flex rounded-md shadow-sm w-full">
                            <input id="{{ config('totp-login.columns.identifier') }}"
                                   name="{{ config('totp-login.columns.identifier') }}"
                                   type="text"
                                   value=""
                                   class="py-2 px-5 block border-gray-300 dark:border-gray-600 border appearance-none focus-primary w-full bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 disabled:cursor-not-allowed rounded-md"
                                   aria-required="true"
                                   autocomplete="{{ config('totp-login.columns.identifier') }}"
                            >
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="w-full flex inline-flex items-center justify-center px-2.5 md:px-5 py-2.5 font-semibold rounded-md text-white bg-green-500 hover:bg-green-400 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer focus:outline-2 focus:outline-offset-2 h-12">
                            Send me the PIN
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
</body>
</html>

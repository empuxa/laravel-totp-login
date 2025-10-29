<?php

use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
use Illuminate\Support\Facades\Config;

describe('CreateAndSendLoginCode Job', function () {
    it('generates code with correct default length', function () {
        Config::set('totp-login.code.length', 6);

        $code = CreateAndSendLoginCode::createCode();

        expect($code)->toBeString();
        expect(strlen($code))->toBe(6);
        expect($code)->toMatch('/^\d{6}$/'); // 6 digits
    });

    it('generates code with custom length', function () {
        Config::set('totp-login.code.length', 8);

        $code = CreateAndSendLoginCode::createCode();

        expect($code)->toBeString();
        expect(strlen($code))->toBe(8);
        expect($code)->toMatch('/^\d{8}$/'); // 8 digits
    });

    it('pads code with leading zeros when necessary', function () {
        Config::set('totp-login.code.length', 6);

        // Generate multiple codes to test padding (statistically some should start with 0)
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = CreateAndSendLoginCode::createCode();
        }

        // All codes should be exactly 6 digits
        foreach ($codes as $code) {
            expect(strlen($code))->toBe(6);
            expect($code)->toMatch('/^\d{6}$/');
        }

        // At least some should start with 0 (statistically)
        $hasLeadingZero = false;
        foreach ($codes as $code) {
            if (str_starts_with($code, '0')) {
                $hasLeadingZero = true;
                break;
            }
        }

        expect($hasLeadingZero)->toBeTrue();
    });

    it('generates different codes on multiple calls', function () {
        Config::set('totp-login.code.length', 6);

        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = CreateAndSendLoginCode::createCode();
        }

        // Check that we have at least 2 different codes (highly unlikely to get 10 identical random codes)
        $uniqueCodes = array_unique($codes);
        expect(count($uniqueCodes))->toBeGreaterThan(1);
    });

    it('generates code within valid range', function () {
        Config::set('totp-login.code.length', 4);

        $code = CreateAndSendLoginCode::createCode();
        $intCode = (int) $code;

        expect($intCode)->toBeGreaterThanOrEqual(0);
        expect($intCode)->toBeLessThanOrEqual(9999);
    });
});

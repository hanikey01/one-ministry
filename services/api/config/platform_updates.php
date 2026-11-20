<?php

return [
    // One Ministry releases are public. Installations must not be pointed at
    // arbitrary repositories from the staff interface.
    'repository' => 'hanikey01/one-ministry',

    // This public key is the immutable trust anchor for package verification.
    // Its matching release-signing private key remains only in GitHub Actions.
    'signing_key_id' => 'one-ministry-rsa-2026-01',
    'public_key_pem' => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAvHBoaKN+4ZK4xI62BlMh
4ZtoxuE9om5Qs708DB88bbU8N9Yn2VLMeaIYYbkpGDExeWe0oQjw7gYhmMR+mSPP
rA9RYAp98Pvcu0KI6zfOOoQIX3LZMFWeRpT5L3dX/MFcMBr2Szbj2IHjf78+a38q
hPxlgmsMI0yzLy8oeweQXgIqUwjoHnI/qvxffGBE1CKuvfe2ldht1ak5KcCnOVOL
7IukZOyLLE2QAhVE8R//4VQ2a98y/uL2tiQcAGgzNqL+wXwmsibL091rZ/Y1WJpW
rTNYpZDdik1zarl0pqV9nLK0P/JhCfjhkOXM+rt1RBeZ+4XBiTIYEXhSuLUy3XMr
vLh8US2Nql+ZBPOIU1QKCnad54ZlAyrb/AB5nvz8WJtzHrbrg/8clFeurm4aH0Cv
wa3A6R7ZrbGrfleanFYaXD4uSyC3CftL1GGmD8OCSCcsAdaHjPTGPdArCiJZfupS
6eBjDwfpwOtrzZyD1DK4RlQWFwbsNFvhcMyJa1PmRX6lB55k1qysz0YdIvunp6Hb
eiSaL2Sc4ocXz0Z16+uTfzu5q0cIMPollD0v2LZDimn8cr732YFSihyQR5ScVgwm
6iKg89knLYSElj2RTx6tB2MniExsjNmGqySd5N1oIwBNRpM8br61zj9OlFLKIaVh
zAk7mZY1jfk0XwJQcyaNN4cCAwEAAQ==
-----END PUBLIC KEY-----
PEM,
];

<?php

use Laragear\TwoFactor\Models\TwoFactorAuthentication;

return TwoFactorAuthentication::migration()->morph('uuid');

# UIX-2 Rollback

Record the clean pre-deploy Aish POS commit and verified backup identifiers before deployment. If rollback is needed, check out that Aish POS commit, run production Composer install, clear and rebuild Laravel caches, restart only the Aish POS queue worker, validate PHP 8.5 FPM/Nginx, and reload those services. No UIX-2 migration exists, so rollback is Git/runtime only.

Repeat root/live/ready, sensitive-path, page, queue, and log checks. Re-run the DaengtisiaMS regression without modifying its repository, database, PHP 8.3 runtime, or workers. Never move an existing GO tag.

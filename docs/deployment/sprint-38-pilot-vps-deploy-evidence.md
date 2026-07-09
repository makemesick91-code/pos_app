# Sprint 38 Pilot/VPS Deploy Evidence

Status: DEPLOY BLOCKED until pilot/VPS target credentials and deployment configuration are available in the environment.

Required before GO:
- target project path
- branch/tag deployment strategy
- PHP/composer/node/npm versions
- database backup command and backup reference
- migration command
- service reload/restart steps
- health check URL/command
- deployed commit/tag verification
- `scripts/sprint38_deploy_smoke.sh` result
- `php artisan performance:deploy-check --confirm-deployed` result
- `php artisan performance:smoke --profile=pilot_vps` result
- `php artisan observability:health` result

Rules covered:
PERF-R001 PERF-R002 PERF-R003 PERF-R004 PERF-R005 PERF-R006 PERF-R007 PERF-R008 PERF-R009 PERF-R010 PERF-R011 PERF-R012 PERF-R013 PERF-R014 PERF-R015 PERF-R016 PERF-R017 PERF-R018 PERF-R019 PERF-R020 PERF-R021 PERF-R022 PERF-R023 PERF-R024 PERF-R025 PERF-R026 PERF-R027 PERF-R028 PERF-R029 PERF-R030 PERF-R031 PERF-R032 PERF-R033 PERF-R034 PERF-R035 PERF-R036

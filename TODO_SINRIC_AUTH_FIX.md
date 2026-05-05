# Sinric Pro Auth Login Fix TODO

## Steps:
1. ✅ Add `use Illuminate\Support\Facades\Http;` import to AuthController.php
2. User: Add `SINRIC_API_KEY=your_sinric_api_key` to .env file
3. Run `php artisan config:clear`
4. Test login endpoint
5. [ ] Check logs if still fails
6. [ ] Verify Sinric creds (email as username?)

# TODO: Complete FormRequests with validation

Using reference: authorize true, rules conditional on method, custom messages.

For each Requests.php:
- authorize(): return true;
- rules(): POST all required with types/exists, PATCH sometimes + unique where appropriate.
- messages(): Custom per rule.

Steps:
1. ✅ Created TODO_REQUESTS.md
2. ☐ FarmsRequests.php
3. ☐ HogPensRequests.php
4. ☐ HogsRequests.php
5. ☐ ... all 16

**Fields/types from $fillable:**
- Farms: user_id exists:users,id |integer, location string max:255, timezone string max:50
- HogPens: farm_id exists:farms,id, name string max:255, capacity integer min:0, status integer
- etc.

Unique examples: Hogs ear_tag_id unique, HogPens name + farm_id unique, etc.


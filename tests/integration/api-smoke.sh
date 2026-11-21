#!/usr/bin/env bash
# End-to-end smoke test of the Groceries OCS API against a running Nextcloud.
# Usage: BASE=http://localhost:8089 A=anna:pass B=ben:pass tests/integration/api-smoke.sh
# Requires: curl, node (for JSON parsing). Users A and B must both be in group "family".
set -euo pipefail
BASE="${BASE:-http://localhost:8089}"
A="${A:?user A as login:password}"
B="${B:?user B as login:password}"
API="$BASE/ocs/v2.php/apps/groceries/api/v1"
fails=0

req() { # user method path [json]
	local user="$1" method="$2" path="$3" body="${4:-}"
	if [ -n "$body" ]; then
		curl -s -u "$user" -X "$method" -H 'OCS-APIRequest: true' -H 'Accept: application/json' -H 'Content-Type: application/json' --data "$body" "$API$path"
	else
		curl -s -u "$user" -X "$method" -H 'OCS-APIRequest: true' -H 'Accept: application/json' "$API$path"
	fi
}
status() { # user method path [json] -> HTTP status
	local user="$1" method="$2" path="$3" body="${4:-}"
	curl -s -o /dev/null -w '%{http_code}' -u "$user" -X "$method" -H 'OCS-APIRequest: true' -H 'Accept: application/json' \
		${body:+-H 'Content-Type: application/json' --data "$body"} "$API$path"
}
q() { node -e "let s='';process.stdin.on('data',d=>s+=d).on('end',()=>{const j=JSON.parse(s).ocs.data;const v=($1);process.stdout.write(typeof v==='object'?JSON.stringify(v):String(v))})"; }
check() { # description actual expected
	if [ "$2" == "$3" ]; then echo "  ok   $1"; else echo "  FAIL $1: expected [$3] got [$2]"; fails=$((fails+1)); fi
}

echo "== lists & sharing"
LIST=$(req "$A" POST /lists '{"name":"Wocheneinkauf","template":"grocery"}' | q 'j.id')
check "create list" "$([ -n "$LIST" ] && echo yes)" yes
check "B cannot see A's private list" "$(status "$B" GET "/lists/$LIST/changes")" 404
req "$A" PUT "/lists/$LIST/members" '{"members":[{"type":"group","id":"family","permission":"edit"}]}' >/dev/null
check "B sees list after group share" "$(req "$B" GET /lists | q "j.lists.some(l=>l.id==$LIST)")" true
check "B is editor" "$(req "$B" GET /lists | q "j.lists.find(l=>l.id==$LIST).permission")" edit
check "B cannot change sharing" "$(status "$B" PUT "/lists/$LIST/members" '{"members":[]}')" 403

echo "== items, parsing, merging, categories"
R0=$(req "$A" GET "/lists/$LIST/changes?since=0" | q 'j.revision')
I1=$(req "$A" POST "/lists/$LIST/items" '{"name":"2 l Milch","opId":"op-1"}')
check "quick-add parses quantity" "$(echo "$I1" | q 'j.item.quantity')" "2 l"
check "catalog category" "$(echo "$I1" | q 'j.item.category')" dairy
check "catalog icon" "$(echo "$I1" | q 'j.item.icon')" "🥛"
MILK=$(echo "$I1" | q 'j.item.id')
check "retry with same opId is idempotent" "$(req "$A" POST "/lists/$LIST/items" '{"name":"2 l Milch","opId":"op-1"}' | q 'j.action')" duplicate
I2=$(req "$B" POST "/lists/$LIST/items" '{"name":"Milch","quantity":"500 ml"}')
check "B adding same item merges" "$(echo "$I2" | q 'j.action + "/" + j.item.id + "/" + j.item.quantity')" "merge/$MILK/2.5 l"
check "unknown item goes to other" "$(req "$A" POST "/lists/$LIST/items" '{"name":"Zauberpulver"}' | q 'j.item.category')" other
req "$A" POST "/lists/$LIST/items" '{"name":"Bananen"}' >/dev/null
CH=$(req "$B" GET "/lists/$LIST/changes?since=$R0")
check "delta sync returns 3 changed items" "$(echo "$CH" | q 'j.items.length')" 3
REV=$(echo "$CH" | q 'j.revision')
check "nothing new since latest revision" "$(req "$B" GET "/lists/$LIST/changes?since=$REV" | q 'j.items.length')" 0

echo "== tick, history, override, checkout"
req "$B" PATCH "/lists/$LIST/items/$MILK" '{"checked":true}' >/dev/null
CH=$(req "$A" GET "/lists/$LIST/changes?since=$REV")
check "A sees B's tick" "$(echo "$CH" | q 'j.items[0].checked + "/" + j.items[0].checkedBy')" "true/${B%%:*}"
ZP=$(req "$A" GET "/lists/$LIST/changes?since=0" | q 'j.items.find(i=>i.name=="Zauberpulver").id')
req "$A" PATCH "/lists/$LIST/items/$ZP" '{"category":"ingredients"}' >/dev/null
req "$A" DELETE "/lists/$LIST/items/$ZP" >/dev/null
check "override remembers category" "$(req "$A" POST "/lists/$LIST/items" '{"name":"zauberpulver"}' | q 'j.item.category')" ingredients
check "history counts uses" "$(req "$A" GET "/lists/$LIST/history" | q 'j.find(h=>h.normName=="c:milk").useCount')" 2
check "re-adding ticked item reopens it" "$(req "$A" POST "/lists/$LIST/items" '{"name":"Milch"}' | q 'j.action + "/" + j.item.checked')" "reopen/false"
req "$A" PATCH "/lists/$LIST/items/$MILK" '{"checked":true}' >/dev/null
check "checkout removes ticked" "$(req "$A" POST "/lists/$LIST/checkout" | q 'j.removed')" 1
REVX=$(req "$A" GET "/lists/$LIST/changes?since=0" | q 'j.revision')

echo "== bulk (cookbook integration)"
# (request bodies stay ASCII: Git Bash on Windows would pass non-ASCII argv to curl in the ANSI codepage)
BODY='{"items":[{"line":"200 g Speck (in Wuerfeln)"},{"line":"1 Prise Salz"},{"line":"3 Eier"},{"line":"100 g Speck"},{"line":"2 Zwiebeln, fein gehackt"},{"line":"500 g Mehl"}],"source":"cookbook","sourceRef":"42","recipeName":"Zwiebelkuchen","dryRun":true}'
DRY=$(req "$A" POST "/lists/$LIST/items/bulk" "$BODY")
check "dry run combines duplicate lines" "$(echo "$DRY" | q 'j.rows.find(r=>r.name=="Speck").quantity')" "300 g"
check "dry run skips staples (salt, flour)" "$(echo "$DRY" | q 'j.rows.filter(r=>r.action=="skip").map(r=>r.name).join(",")')" "Salz,Mehl"
check "dry run writes nothing" "$(req "$A" GET "/lists/$LIST/changes?since=$REVX" | q 'j.items.length')" 0
REAL=$(req "$A" POST "/lists/$LIST/items/bulk" "${BODY/\"dryRun\":true/\"dryRun\":false}")
check "bulk adds 3 items" "$(echo "$REAL" | q 'j.added')" 3
check "note from parentheses/comma" "$(req "$A" GET "/lists/$LIST/changes?since=0" | q 'j.items.find(i=>i.name=="Zwiebeln").note')" "fein gehackt"
check "source label kept" "$(req "$A" GET "/lists/$LIST/changes?since=0" | q 'j.items.find(i=>i.name=="Eier").sourceLabel')" Zwiebelkuchen
check "remove by source" "$(req "$A" DELETE "/lists/$LIST/sources/cookbook/42" | q 'j.removed')" 3
check "parse endpoint" "$(req "$A" POST /parse '{"lines":["1 Dose gehackte Tomaten (400 g)"],"lang":"de"}' | q 'j[0].catalogKey + "/" + j[0].quantity')" "canned_tomatoes/1 Dose"

echo "== stores & prefs"
STORE=$(req "$A" POST "/lists/$LIST/stores" '{"name":"Migros","icon":"🏪","order":["drinks","produce","bogus"]}')
check "store order validated" "$(echo "$STORE" | q 'j.order.join(",")')" "drinks,produce"
SID=$(echo "$STORE" | q 'j.id')
req "$B" PUT "/lists/$LIST/prefs" "{\"activeStoreId\":$SID,\"viewMode\":\"grid\"}" >/dev/null
check "per-user prefs (B)" "$(req "$B" GET "/lists/$LIST/changes?since=0" | q 'j.meta.prefs.viewMode + "/" + j.meta.prefs.activeStoreId')" "grid/$SID"
check "per-user prefs (A untouched)" "$(req "$A" GET "/lists/$LIST/changes?since=0" | q 'j.meta.prefs.viewMode')" list
check "meta contains people" "$(req "$A" GET "/lists/$LIST/changes?since=0" | q 'Object.keys(j.meta.people).sort().join(",")')" "${A%%:*},${B%%:*}"

echo "== capability, page, manifest, service worker"
CAP=$(curl -s -u "$A" -H 'OCS-APIRequest: true' -H 'Accept: application/json' "$BASE/ocs/v2.php/cloud/capabilities" | q 'j.capabilities.groceries.features.includes("bulk-add")')
check "capability advertised" "$CAP" true
check "shell page is public" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/index.php/apps/groceries/")" 200
check "shell has nonce'd module script" "$(curl -s "$BASE/index.php/apps/groceries/" | grep -c 'type="module".*nonce=')" 1
check "manifest" "$(curl -s "$BASE/index.php/apps/groceries/manifest.webmanifest" | node -e "let s='';process.stdin.on('data',d=>s+=d).on('end',()=>process.stdout.write(JSON.parse(s).display))")" standalone
check "service worker served as JS" "$(curl -s -o /dev/null -w '%{content_type}' "$BASE/index.php/apps/groceries/sw.js" | cut -d';' -f1)" application/javascript
check "service worker placeholders replaced" "$(curl -s "$BASE/index.php/apps/groceries/sw.js" | grep -cE '__(CACHE_VERSION|SHELL_URL|PRECACHE|APP_ASSET_PREFIX)__')" 0

echo "== list types have their own categories"
PH=$(req "$A" POST /lists '{"name":"Apotheke","template":"pharmacy"}' | q 'j.id')
check "pharmacy item in pharmacy category" "$(req "$A" POST "/lists/$PH/items" '{"name":"Ibuprofen"}' | q 'j.item.category')" pharmacy_pain
check "grocery item on pharmacy list -> own items" "$(req "$A" POST "/lists/$PH/items" '{"name":"Milch"}' | q 'j.item.category')" other
check "grocery category rejected on pharmacy list" "$(status "$A" PATCH "/lists/$PH/items/$(req "$A" GET "/lists/$PH/changes?since=0" | q 'j.items[0].id')" '{"category":"dairy"}')" 400
check "pharmacy store order keeps only pharmacy categories" "$(req "$A" POST "/lists/$PH/stores" '{"name":"Apotheke Mitte","order":["dairy","first_aid","pharmacy_pain"]}' | q 'j.order.join(",")')" "first_aid,pharmacy_pain"
check "default pharmacy store order has no grocery departments" "$(req "$A" POST "/lists/$PH/stores" '{"name":"Drogerie"}' | q 'j.order.includes("dairy") + "/" + j.order.includes("dental")')" "false/true"
req "$A" PUT "/lists/$PH" '{"template":"grocery"}' >/dev/null
check "changing list type recategorizes items" "$(req "$A" GET "/lists/$PH/changes?since=0" | q 'j.items.map(i=>i.name+":"+i.category).sort().join(",")')" "Ibuprofen:care,Milch:other"
HW=$(req "$A" POST /lists '{"name":"Baumarkt","template":"hardware"}' | q 'j.id')
check "hardware item in hardware category" "$(req "$A" POST "/lists/$HW/items" '{"name":"Schrauben"}' | q 'j.item.category')" fasteners
req "$A" DELETE "/lists/$PH" >/dev/null; req "$A" DELETE "/lists/$HW" >/dev/null
check "grouped preference" "$(req "$A" PUT "/lists/$LIST/prefs" '{"grouped":false}' | q 'j.grouped')" false
check "added-by off by default" "$(req "$A" PUT "/lists/$LIST/prefs" '{}' | q 'j.showAddedBy')" false
check "added-by preference" "$(req "$A" PUT "/lists/$LIST/prefs" '{"showAddedBy":true}' | q 'j.showAddedBy')" true

echo "== purchase log (statistics)"
EV=$(req "$A" POST /lists '{"name":"Statistik","template":"grocery"}' | q 'j.id')
req "$A" PUT "/lists/$EV/members" '{"members":[{"type":"group","id":"family","permission":"edit"}]}' >/dev/null
EI=$(req "$A" POST "/lists/$EV/items" '{"name":"Milch"}' | q 'j.item.id')
req "$B" PATCH "/lists/$EV/items/$EI" '{"checked":true}' >/dev/null
check "add and check are logged with user" "$(req "$A" GET "/lists/$EV/events" | q 'j.map(e=>e.type+":"+e.user).join(",")')" "add:${A%%:*},check:${B%%:*}"
req "$B" PATCH "/lists/$EV/items/$EI" '{"checked":false}' >/dev/null
check "unticking removes the purchase" "$(req "$A" GET "/lists/$EV/events" | q 'j.filter(e=>e.type=="check").length')" 0
EXPORT=$(req "$A" GET "/lists/$EV/events" | q 'j')
check "re-import skips duplicates" "$(req "$A" POST "/lists/$EV/events" "{\"events\":$EXPORT}" | q 'j.imported+"/"+j.skipped')" "0/1"
check "import adds new events" "$(req "$A" POST "/lists/$EV/events" '{"events":[{"id":"imp-1","user":"oma","type":"check","normName":"c:milk","name":"Milch","category":"dairy","at":1700000000}]}' | q 'j.imported')" 1
req "$A" PUT "/lists/$EV/members" '{"members":[{"type":"group","id":"family","permission":"view"}]}' >/dev/null
check "read-only member can see statistics" "$(status "$B" GET "/lists/$EV/events")" 200
check "read-only member cannot import" "$(status "$B" POST "/lists/$EV/events" '{"events":[]}')" 403
req "$A" DELETE "/lists/$EV" >/dev/null

echo "== cleanup"
check "B cannot delete A's list" "$(status "$B" DELETE "/lists/$LIST")" 403
check "owner deletes list" "$(status "$A" DELETE "/lists/$LIST")" 200

echo
if [ "$fails" -eq 0 ]; then echo "ALL PASSED"; else echo "$fails FAILED"; exit 1; fi

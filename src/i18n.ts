import { config } from './config'
import type { Lang } from './lib/catalog'

/** German translations; English is the key. */
const de: Record<string, string> = {
	'Lists': 'Listen',
	'Grocery lists': 'Einkaufslisten',
	'Sort lists': 'Listen sortieren',
	'New list': 'Neue Liste',
	'Create list': 'Liste erstellen',
	'Create': 'Erstellen',
	'Cancel': 'Abbrechen',
	'Save': 'Speichern',
	'Delete': 'Löschen',
	'Done': 'Fertig',
	'Close': 'Schließen',
	'Undo': 'Rückgängig',
	'Name': 'Name',
	'Groceries': 'Lebensmittel',
	'Pharmacy': 'Apotheke & Drogerie',
	'Hardware store': 'Baumarkt',
	'Other list': 'Andere Liste',
	'Icon': 'Symbol',
	'Add item': 'Artikel hinzufügen',
	'Add items': 'Artikel hinzufügen',
	'Search or add an item': 'Artikel suchen oder hinzufügen',
	'Add "{name}"': '„{name}“ hinzufügen',
	'Added {name}': '{name} hinzugefügt',
	'Recently used': 'Zuletzt verwendet',
	'Frequently bought': 'Häufig gekauft',
	'Suggestions': 'Vorschläge',
	'Quantity': 'Menge',
	'Note': 'Notiz',
	'Category': 'Kategorie',
	'Edit item': 'Artikel bearbeiten',
	'In the cart ({count})': 'Im Wagen ({count})',
	'Finish shopping': 'Einkauf abschließen',
	'Remove all ticked items from the list?': 'Alle abgehakten Artikel von der Liste entfernen?',
	'Nothing left to buy': 'Alles erledigt',
	'Your list is empty. Tap + to add items.': 'Die Liste ist leer. Tippe auf +, um Artikel hinzuzufügen.',
	'All done – enjoy!': 'Alles im Wagen – super!',
	'List view': 'Listenansicht',
	'Grid view': 'Kachelansicht',
	'Stores': 'Geschäfte',
	'Stores & aisle order': 'Geschäfte & Regalreihenfolge',
	'Default order': 'Standardreihenfolge',
	'New store': 'Neues Geschäft',
	'Store name': 'Name des Geschäfts',
	'Which store are you shopping in?': 'In welchem Geschäft kaufst du ein?',
	'Shopping here': 'Hier einkaufen',
	'Drag the categories into the order you walk through the store.': 'Ziehe die Kategorien in die Reihenfolge, in der du durch das Geschäft gehst.',
	'Rename': 'Umbenennen',
	'Delete store': 'Geschäft löschen',
	'History': 'Verlauf',
	'No history yet.': 'Noch kein Verlauf.',
	'Forget': 'Vergessen',
	'List settings': 'Listeneinstellungen',
	'Settings': 'Einstellungen',
	'Sharing': 'Teilen',
	'Search users and groups': 'Personen und Gruppen suchen',
	'can edit': 'kann bearbeiten',
	'can view': 'kann ansehen',
	'Owner': 'Besitzer',
	'Remove': 'Entfernen',
	'Group': 'Gruppe',
	'Leave list': 'Liste verlassen',
	'Delete list': 'Liste löschen',
	'Delete "{name}" with all items? This cannot be undone.': '„{name}“ mit allen Artikeln löschen? Dies kann nicht rückgängig gemacht werden.',
	'Remove ticked items automatically': 'Abgehakte Artikel automatisch entfernen',
	'after {hours} h': 'nach {hours} Std.',
	'never': 'nie',
	'Keep screen on while shopping': 'Bildschirm beim Einkaufen eingeschaltet lassen',
	'This device': 'Dieses Gerät',
	'Log out on this device': 'Auf diesem Gerät abmelden',
	'Connect to Nextcloud': 'Mit Nextcloud verbinden',
	'Log in once – this device then stays connected.': 'Einmal anmelden – danach bleibt dieses Gerät verbunden.',
	'Waiting for login in the browser window…': 'Warte auf die Anmeldung im Browserfenster…',
	'If no window opened, tap here': 'Falls sich kein Fenster geöffnet hat, hier tippen',
	'Login failed. Please try again.': 'Anmeldung fehlgeschlagen. Bitte erneut versuchen.',
	'Install the app': 'App installieren',
	'Install': 'Installieren',
	'Add Groceries to your home screen: tap {share} and then "Add to Home Screen".': 'Füge Groceries zum Home-Bildschirm hinzu: Tippe auf {share} und dann auf „Zum Home-Bildschirm“.',
	'Get the full-screen app with one tap.': 'Als Vollbild-App mit einem Tipp öffnen.',
	'Offline – changes will sync later': 'Offline – Änderungen werden später synchronisiert',
	'{count} changes waiting to sync': '{count} Änderungen warten auf Synchronisierung',
	'Could not save: {message}': 'Konnte nicht speichern: {message}',
	'Added by {name}': 'Hinzugefügt von {name}',
	'from {source}': 'aus {source}',
	'New category': 'Neue Kategorie',
	'Category name': 'Name der Kategorie',
	'Choose an emoji': 'Emoji wählen',
	'items': 'Artikel',
	'item': 'Artikel',
	'shared': 'geteilt',
	'No lists yet': 'Noch keine Listen',
	'Create your first shopping list to get started.': 'Erstelle deine erste Einkaufsliste.',
	'Update available': 'Update verfügbar',
	'Reload': 'Neu laden',
	'read-only': 'nur lesen',
	'Language': 'Sprache',
	'Automatic (Nextcloud)': 'Automatisch (Nextcloud)',
	'Account': 'Konto',
	'Logged in as {name}': 'Angemeldet als {name}',
	'Log out': 'Abmelden',
	'Log out of Nextcloud on this device? You will need to log in again.': 'Auf diesem Gerät von Nextcloud abmelden? Du musst dich danach erneut anmelden.',
	'App': 'App',
	'Version': 'Version',
	'Choose icon': 'Symbol wählen',
	'Choose image': 'Bild auswählen',
	'Remove image': 'Bild entfernen',
	'Edit store': 'Geschäft bearbeiten',
	'Group by category': 'Nach Kategorien gruppieren',
	'Colour scheme': 'Farbschema',
	'Light design': 'Helles Design',
	'Statistics': 'Statistik',
	'Week': 'Woche',
	'Month': 'Monat',
	'Year': 'Jahr',
	'All time': 'Gesamt',
	'Import': 'Importieren',
	'Export': 'Exportieren',
	'No purchases in this period yet.': 'In diesem Zeitraum wurde noch nichts eingekauft.',
	'Articles bought': 'Gekaufte Artikel',
	'Shopping trips': 'Einkäufe',
	'Articles per trip': 'Artikel pro Einkauf',
	'New articles': 'Neue Artikel',
	'Most bought': 'Am häufigsten gekauft',
	'Who does what': 'Wer macht was',
	'added': 'hinzugefügt',
	'bought': 'gekauft',
	'trips': 'Einkäufe',
	'Departments': 'Abteilungen',
	'Shopping days': 'Einkaufstage',
	'Biggest shopping day: {date} with {count} articles': 'Größter Einkaufstag: {date} mit {count} Artikeln',
	'{days} days with purchases, {added} articles put on the list': '{days} Tage mit Einkäufen, {added} Artikel auf die Liste gesetzt',
	'{imported} entries imported, {skipped} skipped': '{imported} Einträge importiert, {skipped} übersprungen',
	'Import failed: {message}': 'Import fehlgeschlagen: {message}',
	'Your shopping year {year} is ready': 'Dein Einkaufsjahr {year} ist da',
	'A short look back at what your household bought this year.': 'Ein kurzer Rückblick darauf, was euer Haushalt dieses Jahr eingekauft hat.',
	'Show my year': 'Jahresrückblick ansehen',
	'Later': 'Später',
	'Your shopping year': 'Dein Einkaufsjahr',
	'articles bought': 'Artikel gekauft',
	'on {trips} shopping trips': 'bei {trips} Einkäufen',
	'Your favourites': 'Eure Favoriten',
	'Team household': 'Team Haushalt',
	'went shopping most often: {trips} times': 'war am häufigsten einkaufen: {trips} Mal',
	'{bought} bought · {added} added': '{bought} gekauft · {added} hinzugefügt',
	'Your shopping habits': 'Eure Einkaufsgewohnheiten',
	'Most bought department: {name}': 'Beliebteste Abteilung: {name}',
	'Favourite shopping day: {day}': 'Lieblings-Einkaufstag: {day}',
	'{count} articles per trip on average': 'Im Schnitt {count} Artikel pro Einkauf',
	'Did you know?': 'Schon gewusst?',
	'{count} articles were new on your lists': '{count} Artikel waren neu auf euren Listen',
	'Busiest list: {name} ({count} articles)': 'Fleißigste Liste: {name} ({count} Artikel)',
	'See you next year!': 'Bis nächstes Jahr!',
	'Play as story': 'Als Story ansehen',
	'Back to Nextcloud': 'Zurück zu Nextcloud',
	'Help': 'Hilfe',
	'Quick guide': 'Kurzanleitung',
	'Welcome!': 'Willkommen!',
	'Shared shopping lists for the whole family. This is how it works:': 'Gemeinsame Einkaufslisten für die ganze Familie. So funktioniert es:',
	'Create a list with ＋ at the top, e.g. for the weekly shop, the pharmacy or the hardware store.': 'Lege oben mit ＋ eine Liste an, zum Beispiel für den Wocheneinkauf, die Apotheke oder den Baumarkt.',
	'In a list, tap the round ＋ button and search. You can type the amount right away, e.g. "2 l milk".': 'Tippe in einer Liste auf den runden ＋-Knopf und suche. Die Menge kannst du gleich mittippen, z. B. „2 l Milch“.',
	'Shopping': 'Einkaufen',
	'Tap an item as soon as it is in the cart. A long press edits amount, note and department.': 'Tippe einen Artikel an, sobald er im Wagen liegt. Lange drücken öffnet Menge, Notiz und Abteilung.',
	'Together': 'Gemeinsam',
	'Share a list under ⋯ → List settings. Changes show up for everyone within seconds.': 'Teile eine Liste unter ⋯ → Listeneinstellungen. Änderungen sehen alle nach wenigen Sekunden.',
	'Sorted like your store': 'Sortiert wie dein Laden',
	'Under ⋯ → Stores & aisle order you set the order of the departments in your store.': 'Unter ⋯ → Geschäfte & Regalreihenfolge legst du fest, in welcher Reihenfolge die Abteilungen in deinem Laden kommen.',
	"Let's go": 'Los geht’s',
	'You can open this guide again in the settings (⚙).': 'Du findest diese Anleitung jederzeit in den Einstellungen (⚙).',
	'Use it on your phone': 'Auf dem Handy nutzen',
	'Scan the code with the phone camera, open the link and log in once. Then add the app to the home screen:': 'Scanne den Code mit der Handykamera, öffne den Link und melde dich einmal an. Danach die App zum Home-Bildschirm hinzufügen:',
	'Safari → Share → "Add to Home Screen"': 'Safari → Teilen → „Zum Home-Bildschirm“',
	'Chrome → ⋮ → "Install app"': 'Chrome → ⋮ → „App installieren“',
	'QR code with the address of the app': 'QR-Code mit der Adresse der App',
	'This connection is not encrypted (no HTTPS). Your password and lists could be read by others on the network. Ask the administrator to set up HTTPS.':
		'Diese Verbindung ist nicht verschlüsselt (kein HTTPS). Andere im Netzwerk könnten dein Passwort und deine Listen mitlesen. Bitte die Administration, HTTPS einzurichten.',
	'Connect anyway': 'Trotzdem verbinden',
	'The last 7 days': 'Die letzten 7 Tage',
	'The last 30 days': 'Die letzten 30 Tage',
	'The last 12 months': 'Die letzten 12 Monate',
	'Happy shopping!': 'Viel Spaß beim Einkaufen!',
	'No purchases recorded in {year} yet.': 'Für {year} wurden noch keine Einkäufe erfasst.',
	'Color': 'Farbe',
	'Green': 'Grün',
	'Blue': 'Blau',
	'Red': 'Rot',
	'Purple': 'Lila',
	'Add': 'Hinzufügen',
	'Back': 'Zurück',
	'OK': 'OK',
	'Show frequently bought': '„Häufig gekauft“ anzeigen',
	'Show who added items': 'Anzeigen, wer Artikel hinzugefügt hat',
	'Update': 'Aktualisieren',
	'Updated {name}': '{name} aktualisiert',
	'Already on the list': 'Bereits auf der Liste',
	'Long press an item to set the amount': 'Lange drücken, um die Menge festzulegen',
}

const dictionaries: Record<Lang, Record<string, string>> = { en: {}, de }

/** For the translation completeness test. */
export const germanKeys = () => Object.keys(de)

export type LanguagePreference = 'auto' | Lang
const LANG_KEY = 'groceries:language'

/** Language chosen in the app's settings on this device ('auto' = follow Nextcloud/browser). */
export function getLanguagePreference(): LanguagePreference {
	try {
		const v = localStorage.getItem(LANG_KEY)
		return v === 'de' || v === 'en' ? v : 'auto'
	} catch {
		return 'auto'
	}
}

/** Stores the preference; callers reload the page to apply it everywhere. */
export function setLanguagePreference(pref: LanguagePreference) {
	try {
		if (pref === 'auto') localStorage.removeItem(LANG_KEY)
		else localStorage.setItem(LANG_KEY, pref)
	} catch {
		// private mode: not persisted
	}
}

function detect(): Lang {
	const pref = typeof localStorage !== 'undefined' ? getLanguagePreference() : 'auto'
	if (pref !== 'auto') return pref
	if (config.language === 'de' || config.language === 'en') return config.language
	return typeof navigator !== 'undefined' && navigator.language?.startsWith('de') ? 'de' : 'en'
}

export const lang: Lang = detect()

export function t(key: string, vars: Record<string, string | number> = {}): string {
	const s = dictionaries[lang][key] ?? key
	return s.replace(/\{(\w+)\}/g, (_, k) => String(vars[k] ?? `{${k}}`))
}

export function n(count: number, singular: string, plural: string): string {
	return `${count} ${t(count === 1 ? singular : plural)}`
}

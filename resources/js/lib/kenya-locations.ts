import type { MultiSelectOption } from "@/components/ui/multi-select"

// Kenya's 47 counties, each with the towns job ads commonly name instead of
// the county. Job matching compares these names against a posting's location
// text, so a fixed spelling matches far better than free typing.
const COUNTIES: Record<string, string[]> = {
	Nairobi: ["Westlands", "Upper Hill", "Karen", "Embakasi"],
	Mombasa: ["Nyali", "Likoni"],
	Kwale: ["Diani", "Ukunda"],
	Kilifi: ["Malindi", "Watamu", "Mtwapa"],
	"Tana River": ["Hola"],
	Lamu: [],
	"Taita Taveta": ["Voi", "Wundanyi"],
	Garissa: [],
	Wajir: [],
	Mandera: [],
	Marsabit: ["Moyale"],
	Isiolo: [],
	Meru: ["Maua"],
	"Tharaka Nithi": ["Chuka"],
	Embu: [],
	Kitui: ["Mwingi"],
	Machakos: ["Athi River", "Syokimau", "Mlolongo"],
	Makueni: ["Wote", "Mtito Andei"],
	Nyandarua: ["Ol Kalou"],
	Nyeri: ["Karatina", "Othaya"],
	Kirinyaga: ["Kerugoya", "Mwea"],
	"Murang'a": ["Kenol"],
	Kiambu: ["Thika", "Ruiru", "Juja", "Kikuyu", "Limuru"],
	Turkana: ["Lodwar", "Kakuma"],
	"West Pokot": ["Kapenguria"],
	Samburu: ["Maralal"],
	"Trans Nzoia": ["Kitale"],
	"Uasin Gishu": ["Eldoret"],
	"Elgeyo Marakwet": ["Iten"],
	Nandi: ["Kapsabet"],
	Baringo: ["Kabarnet", "Eldama Ravine"],
	Laikipia: ["Nanyuki", "Nyahururu"],
	Nakuru: ["Naivasha", "Gilgil", "Molo"],
	Narok: [],
	Kajiado: ["Kitengela", "Ngong", "Rongai", "Kiserian"],
	Kericho: ["Litein"],
	Bomet: [],
	Kakamega: ["Mumias"],
	Vihiga: ["Mbale"],
	Bungoma: ["Webuye", "Kimilili"],
	Busia: ["Malaba"],
	Siaya: ["Bondo"],
	Kisumu: ["Ahero"],
	"Homa Bay": ["Mbita"],
	Migori: ["Awendo", "Rongo"],
	Kisii: [],
	Nyamira: [],
}

export const KENYA_LOCATIONS: MultiSelectOption[] = Object.entries(COUNTIES).flatMap(
	([county, towns]) => [
		{ value: county, hint: "County" },
		...towns.map((town) => ({ value: town, hint: county })),
	]
)

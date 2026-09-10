import 'package:flutter/material.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/theme/dimens.dart';
import '../legal_content.dart';

/// Standalone, always-reachable Terms of Service (from Profile, and from
/// the acceptance gate at signup). CLAUDE.md's data model records
/// acceptance as a timestamp + version, not the document text itself, so
/// this content lives in the app rather than being fetched — same
/// pattern most apps use for long-form legal text, versus the ARB
/// catalog used for UI microcopy (see DECISIONS.md).
class TermsScreen extends StatelessWidget {
  const TermsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.legalTermsTitle)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(SokoniDimens.space20),
        child: Text(locale == 'sw' ? _swBody : _enBody, style: Theme.of(context).textTheme.bodyMedium),
      ),
    );
  }
}

const _enBody = '''
Terms of Service — version $sokoniTermsVersion

Last updated: 2026

1. Who we are
Sokoni ("we", "us") operates the Sokoni marketplace app and website, connecting buyers and sellers in Tanzania. These Terms govern your use of Sokoni.

2. Your account
You may browse Sokoni without an account. Creating an account (via phone number, Google, or Apple) is required to place an order, message a seller, or save favourites. You're responsible for keeping your account credentials secure and for all activity under your account.

3. Buying on Sokoni
Sokoni is a marketplace connecting buyers and sellers — we are not a party to the sale itself. Orders, pricing, delivery and payment terms are agreed between you and the seller. Payment on the platform today is cash on delivery or pay on pickup only.

4. Selling on Sokoni
To sell, you must complete seller registration, including providing a valid NIDA (National Identification Authority) number. This information is used solely for manual identity verification by our team and is never shown publicly. Submitting false or fraudulent identity information will result in account suspension or termination.

Your shop and products may be visible to buyers once your account is verified. Until verification, your products remain hidden from public search and browsing, though you may continue building your shop.

You agree to list only genuine business content — products you're actually selling, your shop, and your work. Personal, non-business content is not permitted.

5. Reviews
Buyers may leave a review only after completing an order with a seller. Reviews must reflect a genuine experience. Sellers may reply once to each review. We reserve the right to remove reviews that violate these Terms.

6. Prohibited conduct
You may not: list prohibited or illegal goods; impersonate another person or business; harass, threaten, or abuse other users; submit fraudulent reviews, reports, or verification documents; or attempt to circumvent our moderation or verification systems.

7. Content moderation
We may hide, warn, suspend, or ban accounts that violate these Terms, with reasons communicated to the affected user. Content reported and upheld three or more times is automatically hidden pending review.

8. Location and communication
Some features (nearby search, delivery) use your device location, which you can decline — Sokoni falls back to manual region/district selection. In-app messages between buyers and sellers may be read by our moderation team if reported.

9. Termination
You may stop using Sokoni at any time. We may suspend or terminate accounts that violate these Terms.

10. Changes to these Terms
We may update these Terms from time to time. Material changes will require you to accept the new version before continuing to use Sokoni.

11. Governing law
These Terms are governed by the laws of the United Republic of Tanzania.

12. Contact
Questions about these Terms can be sent to support@sokoni.co.tz.
''';

const _swBody = '''
Masharti ya Huduma — toleo $sokoniTermsVersion

Yalisasishwa mwisho: 2026

1. Sisi ni akina nani
Sokoni ("sisi") inaendesha programu na tovuti ya soko la Sokoni, inayounganisha wanunuzi na wauzaji nchini Tanzania. Masharti haya yanasimamia matumizi yako ya Sokoni.

2. Akaunti yako
Unaweza kutazama Sokoni bila akaunti. Kufungua akaunti (kwa nambari ya simu, Google, au Apple) inahitajika ili kuagiza, kuzungumza na muuzaji, au kuhifadhi vipendwa. Wewe ndiye unayehusika na usalama wa taarifa za akaunti yako na shughuli zote chini yake.

3. Kununua kwenye Sokoni
Sokoni ni soko linalounganisha wanunuzi na wauzaji — sisi si sehemu ya mauzo yenyewe. Oda, bei, uwasilishaji na masharti ya malipo hukubaliwa kati yako na muuzaji. Malipo kwa sasa ni malipo baada ya kuletewa au wakati wa kuchukua pekee.

4. Kuuza kwenye Sokoni
Ili kuuza, lazima ukamilishe usajili wa muuzaji, ukiwemo kutoa namba halali ya NIDA. Taarifa hii hutumika tu kwa uthibitishaji wa kibinafsi na timu yetu na haionyeshwi hadharani. Kuwasilisha taarifa za uongo za utambulisho kutasababisha kusimamishwa au kufutwa kwa akaunti.

Duka na bidhaa zako zinaweza kuonekana kwa wanunuzi mara akaunti yako itakapothibitishwa. Kabla ya uthibitisho, bidhaa zako hazitaonekana kwenye utafutaji wa hadharani, ingawa unaweza kuendelea kujenga duka lako.

Unakubali kuweka maudhui ya biashara halisi tu — bidhaa unazouza, duka lako, na kazi yako. Maudhui binafsi yasiyo ya biashara hayaruhusiwi.

5. Maoni
Wanunuzi wanaweza kutoa maoni tu baada ya kukamilisha oda na muuzaji. Maoni lazima yaakisi uzoefu halisi. Wauzaji wanaweza kujibu mara moja kwa kila maoni. Tunahifadhi haki ya kuondoa maoni yanayokiuka Masharti haya.

6. Tabia zisizoruhusiwa
Huruhusiwi: kuorodhesha bidhaa haramu; kujifanya mtu au biashara nyingine; kunyanyasa au kutishia watumiaji wengine; kuwasilisha maoni, ripoti, au hati za uthibitisho za uongo; au kujaribu kupita mifumo yetu ya usimamizi.

7. Usimamizi wa maudhui
Tunaweza kuficha, kuonya, kusimamisha, au kupiga marufuku akaunti zinazokiuka Masharti haya, tukieleza sababu kwa mtumiaji husika. Maudhui yaliyoripotiwa na kuthibitishwa mara tatu au zaidi hufichwa moja kwa moja yakisubiri ukaguzi.

8. Mahali na mawasiliano
Baadhi ya vipengele (utafutaji wa karibu, uwasilishaji) hutumia mahali pa kifaa chako, ambao unaweza kukataa — Sokoni itarudi kwenye uchaguzi wa mkoa/wilaya kwa mkono. Ujumbe wa ndani ya programu kati ya wanunuzi na wauzaji unaweza kusomwa na timu yetu ya usimamizi ikiwa umeripotiwa.

9. Kusitisha
Unaweza kuacha kutumia Sokoni wakati wowote. Tunaweza kusimamisha au kufuta akaunti zinazokiuka Masharti haya.

10. Mabadiliko ya Masharti haya
Tunaweza kusasisha Masharti haya mara kwa mara. Mabadiliko makubwa yatahitaji ukubali toleo jipya kabla ya kuendelea kutumia Sokoni.

11. Sheria inayotawala
Masharti haya yanatawaliwa na sheria za Jamhuri ya Muungano wa Tanzania.

12. Mawasiliano
Maswali kuhusu Masharti haya yanaweza kutumwa kwa support@sokoni.co.tz.
''';

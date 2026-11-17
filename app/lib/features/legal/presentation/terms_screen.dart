import 'package:flutter/material.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/theme/dimens.dart';
import '../legal_content.dart';

/// Standalone, always-reachable Terms of Use (from Profile, and from the
/// Terms step before sign-in). CLAUDE.md's data model records acceptance
/// as a timestamp + version, not the document text itself, so this
/// content lives in the app rather than being fetched — same pattern most
/// apps use for long-form legal text, versus the ARB catalog used for UI
/// microcopy (see DECISIONS.md). Same text as the website's /terms page
/// (api/resources/views/web/pages/terms.blade.php); clause 2 is the
/// Apple Guideline 1.2 zero-tolerance clause added in version 1.1.
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
Terms of Use (EULA) — version $sokoniTermsVersion

Last updated: November 2026

1. Who we are
Sokoni ("we", "us") operates the Sokoni marketplace app and website, connecting buyers and sellers in Tanzania. These Terms are the licence agreement between you and Sokoni (EULA) and govern your use of Sokoni.

2. Zero tolerance for objectionable content and abusive users
Sokoni has zero tolerance for objectionable content and abusive users. You must not post, upload or send anything offensive, sexual, violent, hateful, harassing, threatening, discriminatory, fraudulent or illegal — in products, photos, videos, shop profiles, comments, reviews, messages or your profile — and you must not abuse, harass or threaten other users.

You can report any content or user from the "⋯" menu next to it, and you can block any user. When you block someone, their shop, products, posts, comments, reviews and messages disappear from your view at once, neither of you can message or order from the other, and our team is notified.

We review every report within 24 hours. Content that breaks these rules is removed, and the user who posted it is removed from Sokoni (their account is banned). We may also act without a report.

3. Your account
You may browse Sokoni without an account. Creating an account (via phone number, Google, or Apple) is required to place an order, message a seller, or save favourites. You must agree to these Terms before you sign in or create an account. You're responsible for keeping your account credentials secure and for all activity under your account.

4. Buying on Sokoni
Sokoni is a marketplace connecting buyers and sellers — we are not a party to the sale itself. Orders, pricing, delivery and payment terms are agreed between you and the seller. Payment on the platform today is cash on delivery or pay on pickup only.

5. Selling on Sokoni
To sell, you must complete seller registration, including providing a valid NIDA (National Identification Authority) number. This information is used solely for manual identity verification by our team and is never shown publicly. Submitting false or fraudulent identity information will result in account suspension or termination.

Your shop and products may be visible to buyers once your account is verified. Until verification, your products remain hidden from public search and browsing, though you may continue building your shop.

You agree to list only genuine business content — products you're actually selling, your shop, and your work. Personal, non-business content is not permitted.

6. Reviews
Buyers may leave a review only after completing an order with a seller. Reviews must reflect a genuine experience. Sellers may reply once to each review. We reserve the right to remove reviews that violate these Terms.

7. Prohibited conduct
You may not: list prohibited or illegal goods; impersonate another person or business; harass, threaten, or abuse other users; submit fraudulent reviews, reports, or verification documents; or attempt to circumvent our moderation or verification systems.

8. Content moderation
We may hide or remove content and warn, suspend, or ban accounts that violate these Terms, and we tell the affected user why. Reported content is reviewed within 24 hours; we do not wait for more reports before acting. Content may also be hidden automatically while it is reviewed.

9. Location and communication
Some features (nearby search, delivery) use your device location, which you can decline — Sokoni falls back to manual region/district selection. In-app messages between buyers and sellers may be read by our moderation team if reported.

10. Termination
You may stop using Sokoni at any time. We may suspend or terminate accounts that violate these Terms.

11. Changes to these Terms
We may update these Terms from time to time. Material changes will require you to accept the new version before continuing to use Sokoni.

12. Governing law
These Terms are governed by the laws of the United Republic of Tanzania.

13. Contact
Questions about these Terms can be sent to support@sokoni.co.tz.
''';

const _swBody = '''
Masharti ya Matumizi (EULA) — toleo $sokoniTermsVersion

Yalisasishwa mwisho: Novemba 2026

1. Sisi ni akina nani
Sokoni ("sisi") inaendesha programu na tovuti ya soko la Sokoni, inayounganisha wanunuzi na wauzaji nchini Tanzania. Masharti haya ni makubaliano ya leseni kati yako na Sokoni (EULA) na yanasimamia matumizi yako ya Sokoni.

2. Hatuvumilii kabisa maudhui yasiyofaa wala watumiaji wanaonyanyasa wengine
Sokoni haivumilii kabisa maudhui yasiyofaa wala watumiaji wanaonyanyasa wengine. Usiweke, usipakie wala usitume chochote cha matusi, ngono, vurugu, chuki, unyanyasaji, vitisho, ubaguzi, udanganyifu au kilicho kinyume cha sheria — kwenye bidhaa, picha, video, wasifu wa duka, maoni, tathmini, ujumbe au wasifu wako — na usiwanyanyase, usiwasumbue wala usiwatishie watumiaji wengine.

Unaweza kuripoti maudhui au mtumiaji yeyote kupitia menyu ya "⋯" iliyo kando yake, na unaweza kumzuia mtumiaji yeyote. Ukimzuia mtu, duka lake, bidhaa, machapisho, maoni, tathmini na ujumbe wake hupotea mara moja kwenye skrini yako, hakuna kati yenu atakayeweza kumtumia mwenzake ujumbe wala kuagiza kutoka kwake, na timu yetu hujulishwa.

Tunakagua kila ripoti ndani ya saa 24. Maudhui yanayovunja sheria hizi huondolewa, na mtumiaji aliyeyaweka huondolewa kwenye Sokoni (akaunti yake hufungiwa). Tunaweza pia kuchukua hatua bila ripoti.

3. Akaunti yako
Unaweza kutazama Sokoni bila akaunti. Kufungua akaunti (kwa nambari ya simu, Google, au Apple) inahitajika ili kuagiza, kuzungumza na muuzaji, au kuhifadhi vipendwa. Lazima ukubali Masharti haya kabla ya kuingia au kufungua akaunti. Wewe ndiye unayehusika na usalama wa taarifa za akaunti yako na shughuli zote chini yake.

4. Kununua kwenye Sokoni
Sokoni ni soko linalounganisha wanunuzi na wauzaji — sisi si sehemu ya mauzo yenyewe. Oda, bei, uwasilishaji na masharti ya malipo hukubaliwa kati yako na muuzaji. Malipo kwa sasa ni malipo baada ya kuletewa au wakati wa kuchukua pekee.

5. Kuuza kwenye Sokoni
Ili kuuza, lazima ukamilishe usajili wa muuzaji, ukiwemo kutoa namba halali ya NIDA. Taarifa hii hutumika tu kwa uthibitishaji wa kibinafsi na timu yetu na haionyeshwi hadharani. Kuwasilisha taarifa za uongo za utambulisho kutasababisha kusimamishwa au kufutwa kwa akaunti.

Duka na bidhaa zako zinaweza kuonekana kwa wanunuzi mara akaunti yako itakapothibitishwa. Kabla ya uthibitisho, bidhaa zako hazitaonekana kwenye utafutaji wa hadharani, ingawa unaweza kuendelea kujenga duka lako.

Unakubali kuweka maudhui ya biashara halisi tu — bidhaa unazouza, duka lako, na kazi yako. Maudhui binafsi yasiyo ya biashara hayaruhusiwi.

6. Tathmini
Wanunuzi wanaweza kutoa tathmini tu baada ya kukamilisha oda na muuzaji. Tathmini lazima ziakisi uzoefu halisi. Wauzaji wanaweza kujibu mara moja kwa kila tathmini. Tunahifadhi haki ya kuondoa tathmini zinazokiuka Masharti haya.

7. Tabia zisizoruhusiwa
Huruhusiwi: kuorodhesha bidhaa haramu; kujifanya mtu au biashara nyingine; kunyanyasa au kutishia watumiaji wengine; kuwasilisha maoni, ripoti, au hati za uthibitisho za uongo; au kujaribu kupita mifumo yetu ya usimamizi.

8. Usimamizi wa maudhui
Tunaweza kuficha au kuondoa maudhui na kuonya, kusimamisha, au kufungia akaunti zinazokiuka Masharti haya, tukimweleza mtumiaji husika sababu. Maudhui yaliyoripotiwa hukaguliwa ndani ya saa 24; hatusubiri ripoti zaidi kabla ya kuchukua hatua. Maudhui yanaweza pia kufichwa moja kwa moja wakati yanakaguliwa.

9. Mahali na mawasiliano
Baadhi ya vipengele (utafutaji wa karibu, uwasilishaji) hutumia mahali pa kifaa chako, ambao unaweza kukataa — Sokoni itarudi kwenye uchaguzi wa mkoa/wilaya kwa mkono. Ujumbe wa ndani ya programu kati ya wanunuzi na wauzaji unaweza kusomwa na timu yetu ya usimamizi ikiwa umeripotiwa.

10. Kusitisha
Unaweza kuacha kutumia Sokoni wakati wowote. Tunaweza kusimamisha au kufuta akaunti zinazokiuka Masharti haya.

11. Mabadiliko ya Masharti haya
Tunaweza kusasisha Masharti haya mara kwa mara. Mabadiliko makubwa yatahitaji ukubali toleo jipya kabla ya kuendelea kutumia Sokoni.

12. Sheria inayotawala
Masharti haya yanatawaliwa na sheria za Jamhuri ya Muungano wa Tanzania.

13. Mawasiliano
Maswali kuhusu Masharti haya yanaweza kutumwa kwa support@sokoni.co.tz.
''';

{{--
    Accident payé par une assurance : majoration incluse dans les prix du devis.
    Indication interne seulement — jamais sur le devis ni la facture imprimés.
--}}
@if((float) $devis->taux_majoration > 0)
<div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 text-sm text-indigo-700">
    Accident payé par l'assurance : majoration de <strong>{{ rtrim(rtrim(number_format((float) $devis->taux_majoration, 2, ',', ' '), '0'), ',') }} %</strong>
    @if($edition ?? false)
        appliquée automatiquement à l'enregistrement. Saisissez les prix normaux (hors majoration).
    @else
        incluse dans les prix (pièces et main-d'œuvre). Elle n'apparaît pas sur le devis ni la facture imprimés.
    @endif
</div>
@endif

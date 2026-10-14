{{--
    Regroupement des ingrédients (peinture, antirouille) sur le formulaire du devis :
    on coche des lignes de pièces puis on clique sur un groupe. Chaque pièce garde
    sa ligne (stock, BC, BT) ; seuls le devis et la facture imprimés les fusionnent
    en une ligne « Ingrédients de … » (cf. App\Services\GroupeIngredientsService).
    Les lignes du tableau #lignes-body sont équipées automatiquement, y compris
    celles ajoutées ensuite ; le groupe initial vient de l'attribut data-groupe.
--}}
@php $groupesIngredients = \App\Services\GroupeIngredientsService::GROUPES; @endphp
<div class="px-6 py-3 border-b border-gray-200 bg-white flex flex-wrap items-center gap-2 text-xs">
    <span class="text-slate-500">Pièces cochées :</span>
    @foreach($groupesIngredients as $cle => $libelle)
    <button type="button" onclick="regrouperIngredients('{{ $cle }}')"
            class="px-3 py-1.5 rounded-lg font-semibold border transition-colors {{ $cle === 'peinture' ? 'border-pink-300 text-pink-700 bg-pink-50 hover:bg-pink-100' : 'border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100' }}">
        Regrouper en {{ $libelle }}
    </button>
    @endforeach
    <button type="button" onclick="regrouperIngredients('')"
            class="px-3 py-1.5 rounded-lg border border-gray-300 text-slate-600 hover:bg-gray-50 transition-colors">
        Retirer du groupe
    </button>
    <span class="text-slate-400">— une seule ligne par groupe sur le devis et la facture imprimés (le magasin garde le détail)</span>
</div>

@push('scripts')
<script>
(function () {
    const GROUPES = @json($groupesIngredients);
    const STYLES  = { peinture: 'bg-pink-100 text-pink-700', antirouille: 'bg-amber-100 text-amber-700' };
    const corps   = document.getElementById('lignes-body');

    function typeLigne(row) {
        return row.querySelector('select[name$="[type]"]')?.value;
    }

    function afficherGroupe(row) {
        const groupe = row.querySelector('.ligne-groupe').value;
        const badge  = row.querySelector('.ligne-groupe-badge');
        badge.textContent = groupe ? GROUPES[groupe] : '';
        badge.className   = 'ligne-groupe-badge inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold ' + (groupe ? STYLES[groupe] : 'hidden');
        const caseACocher = row.querySelector('.ligne-groupe-case');
        caseACocher.classList.toggle('invisible', typeLigne(row) !== 'piece');
    }

    // Ajoute à une ligne la case à cocher, le champ caché « groupe » et l'étiquette
    function equiper(row) {
        if (row.querySelector('.ligne-groupe')) return;
        const select = row.querySelector('select[name$="[type]"]');
        if (!select) return;
        const index = select.name.match(/^lignes\[(\d+)\]/)[1];
        const cellule = select.closest('td');

        const entete = document.createElement('div');
        entete.className = 'flex items-center gap-2 mb-1';
        entete.innerHTML = `<input type="checkbox" class="ligne-groupe-case rounded border-gray-300" title="Cocher pour regrouper cette pièce">`
            + `<input type="hidden" name="lignes[${index}][groupe]" class="ligne-groupe" value="${row.dataset.groupe || ''}">`;
        cellule.prepend(entete);

        const badge = document.createElement('span');
        badge.className = 'ligne-groupe-badge hidden';
        cellule.appendChild(badge);

        // Une ligne qui n'est plus une pièce sort de son groupe
        select.addEventListener('change', () => {
            if (select.value !== 'piece') row.querySelector('.ligne-groupe').value = '';
            row.querySelector('.ligne-groupe-case').checked = false;
            afficherGroupe(row);
        });
        afficherGroupe(row);
    }

    window.regrouperIngredients = function (groupe) {
        const cochees = [...corps.querySelectorAll('.ligne-row')].filter(r => r.querySelector('.ligne-groupe-case')?.checked);
        if (!cochees.length) {
            alert('Cochez d\'abord les lignes de pièces à regrouper.');
            return;
        }
        cochees.forEach(row => {
            if (typeLigne(row) === 'piece') row.querySelector('.ligne-groupe').value = groupe;
            row.querySelector('.ligne-groupe-case').checked = false;
            afficherGroupe(row);
        });
    };

    corps.querySelectorAll('.ligne-row').forEach(equiper);
    new MutationObserver(mutations => mutations.forEach(m => m.addedNodes.forEach(n => {
        if (n.nodeType === 1 && n.classList.contains('ligne-row')) equiper(n);
    }))).observe(corps, { childList: true });
})();
</script>
@endpush

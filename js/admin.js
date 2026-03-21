( function () {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function () {
		var container = document.getElementById( 'a2e-steps' );
		if ( ! container ) return;

		var opTypes = [ 'ExecuteAbility', 'ApiCall', 'FilterData', 'TransformData', 'Conditional', 'Loop', 'StoreData', 'Wait', 'MergeData' ];

		// Add step
		document.querySelector( '.a2e-add-step' ).addEventListener( 'click', function () {
			var idx = container.querySelectorAll( '.a2e-step' ).length;
			var div = document.createElement( 'div' );
			div.className = 'a2e-step';
			div.dataset.index = idx;
			div.innerHTML = '<div class="a2e-step-header"><strong>Step ' + ( idx + 1 ) + '</strong>' +
				'<button type="button" class="button a2e-remove-step" title="Remove">&times;</button></div>' +
				'<table class="form-table a2e-step-fields">' +
				'<tr><th>ID</th><td><input type="text" name="steps[' + idx + '][id]" value="step_' + idx + '" class="regular-text" required pattern="[a-z0-9_]+"></td></tr>' +
				'<tr><th>Type</th><td><select name="steps[' + idx + '][type]" class="a2e-step-type">' +
				opTypes.map( function ( t ) { return '<option value="' + t + '">' + t + '</option>'; } ).join( '' ) +
				'</select></td></tr>' +
				'<tr><th>Config (JSON)</th><td><textarea name="steps[' + idx + '][config]" rows="4" class="large-text" placeholder=\'{"ability": "my/ability", "input": {"key": "/prev_step"}}\'></textarea>' +
				'<p class="description">Step config as JSON.</p></td></tr></table>';
			container.appendChild( div );
		} );

		// Remove step
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'a2e-remove-step' ) ) {
				var step = e.target.closest( '.a2e-step' );
				if ( container.querySelectorAll( '.a2e-step' ).length > 1 ) {
					step.remove();
				}
			}
		} );

		// Auto-generate ID from name
		var nameInput = document.getElementById( 'a2e_name' );
		var idInput = document.getElementById( 'a2e_id' );
		if ( nameInput && idInput && ! idInput.readOnly ) {
			nameInput.addEventListener( 'input', function () {
				idInput.value = nameInput.value.toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_|_$/g, '' );
			} );
		}
	} );
} )();

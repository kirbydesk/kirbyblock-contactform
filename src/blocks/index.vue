<template>
	<!-- the block in the panel: its info bar and the shared preview of the
	     Project Wizard (the project's look, the fields as set); a double
	     click opens the block -->
	<div class="pwPreview" data-kirbyblock="contactform" @dblclick="open">

		<pwBlockinfo
			:value="$t('kirbyblock-contactform.name')"
			:design="'pwcontactform'"
			icon="contactform"
		/>

		<pw-block-panel-preview type="pwcontactform" :content="content" />

	</div>
</template>

<script>
import pwBlockinfo from '@/../../kirby-pagewizard/src/components/blockinfo.vue';

export default {
	components: {
		pwBlockinfo
	}
}
</script>

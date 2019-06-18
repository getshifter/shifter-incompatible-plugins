preview-archive:
	git archive HEAD --format tgz | tar tz

update-gitattributes:
	@git ls-files | grep -v shifter-unrecommended-plugins.php | xargs -I{} echo {} export-ignore > .gitattributes
	cat .gitattributes
	git diff


.PHONY: preview-archive update-gitattributes

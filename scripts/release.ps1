[CmdletBinding()]
param(
    [string]$PhpBinary = 'php',
    [string]$PythonBinary = 'python',
    [string]$NodeBinary = 'node'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$trustedGit = 'C:\Program Files\Git\cmd\git.exe'
$trustedTar = 'C:\Windows\System32\tar.exe'

foreach ($trustedTool in @($trustedGit, $trustedTar)) {
    if (-not (Test-Path -LiteralPath $trustedTool -PathType Leaf)) {
        throw "Trusted release tool is missing: $trustedTool"
    }
}

$pythonCommand = @(Get-Command $PythonBinary -CommandType Application -All -ErrorAction Stop)[0]
$phpCommand = @(Get-Command $PhpBinary -CommandType Application -All -ErrorAction Stop)[0]
$nodeCommand = @(Get-Command $NodeBinary -CommandType Application -All -ErrorAction Stop)[0]
$pythonExecutable = $pythonCommand.Source
$phpExecutable = $phpCommand.Source
$nodeExecutable = $nodeCommand.Source

$detectedRoot = (& $trustedGit -C $repositoryRoot rev-parse --show-toplevel).Trim()
if ([IO.Path]::GetFullPath($detectedRoot) -ne [IO.Path]::GetFullPath($repositoryRoot)) {
    throw "Git root mismatch: $detectedRoot"
}

$dirty = (& $trustedGit -C $repositoryRoot status --porcelain=v1 --untracked-files=all)
if ($dirty) {
    throw "Release source must start from a clean Git worktree.`n$dirty"
}

$sourceCommit = (& $trustedGit -C $repositoryRoot rev-parse HEAD).Trim().ToLowerInvariant()
if ($sourceCommit -notmatch '^[0-9a-f]{40}$' -or $sourceCommit -eq '0000000000000000000000000000000000000000') {
    throw 'Could not resolve a full source commit.'
}

$versionReader = Join-Path $repositoryRoot 'scripts\read_release_version.py'
$versionOutput = & $pythonExecutable $versionReader (Join-Path $repositoryRoot 'release.json')
if ($LASTEXITCODE -ne 0) {
    throw 'Could not read a strict release version.'
}
$version = ($versionOutput | Select-Object -Last 1).Trim()
if ($version -notmatch '^[0-9]+(?:\.[0-9]+)+$') {
    throw 'Could not read a strict release version.'
}

$releaseInputs = @(
    'assets/guides/sources/cannabis-law-thailand-v1-master.png',
    'assets/guides/sources/visas-entry-thailand-v1-master.png',
    'data/content/bangkok-rental-areas.json',
    'data/content/bangkok-rental-areas.schema.json',
    'data/content/priority-guides.json',
    'data/content/priority-guides.schema.json',
    'data/content/real-estate.json',
    'data/content/real-estate.schema.json',
    'data/content/inventory/draft-content-disposition.2026-08-08.csv',
    'data/content/inventory/draft-content-metadata.2026-08-08.csv',
    'data/content/migration/migration-ledger.2026-08-10.json',
    'data/content/migration/migration-ledger.schema.json',
    'data/content/migration/README.md',
    'data/content/migration/urgent-source-review.2026-08-10.json',
    'data/geography/aliases.csv',
    'data/geography/geometry.json',
    'data/geography/normalization-vectors.json',
    'data/geography/provinces.csv',
    'data/geography/regions.json',
    'data/geography/registry.json',
    'data/geography/registry.schema.json',
    'data/geography/relations.json',
    'data/seo/README.md',
    'data/seo/evidence/managed-live-routes.0.3.5.json',
    'data/seo/inventory/current-public-url-metadata.2026-08-08.csv',
    'data/seo/inventory/indexable-category-surfaces.2026-08-08.csv',
    'data/seo/ownership-registry.json',
    'data/seo/ownership-registry.schema.json',
    'data/content/queued/post-136-thailand-rainy-day-activities.json',
    'data/content/queued/post-17-koh-samui-new-hotels-2022.json',
    'data/content/queued/post-732-thailand-family-holiday-costs.json',
    'data/content/queued/post-810-thailand-property-prices-plan.json',
    'output/playwright/homepage-live-0.3.6-acceptance.json',
    'package-files.txt',
    'prototype/app.js',
    'prototype/assets/homepage-hero-thailand-system-v1-1024.webp',
    'prototype/assets/homepage-hero-thailand-system-v1-1713.webp',
    'prototype/assets/homepage-hero-thailand-system-v1-640.webp',
    'prototype/assets/bangkok-rental-atlas-v1.png',
    'prototype/assets/real-estate-thailand-atlas-v1.png',
    'prototype/index.html',
    'prototype/styles.css',
    'release.json',
    'research/serp/2026-08-08-hebrew-thailand-serp.md',
    'scripts/build_bangkok_rental_assets.py',
    'scripts/build_bangkok_rental_registry.py',
    'scripts/build_content_registry.py',
    'scripts/build_content_migration_ledger.py',
    'scripts/build_homepage_assets.py',
    'scripts/build_geography_registry.py',
    'scripts/build_guide_assets.py',
    'scripts/build_seo_registry.py',
    'scripts/build_seo_runtime.py',
    'scripts/build_priority_guides_registry.py',
    'scripts/build_plugin_zip.py',
    'scripts/live_guides_acceptance.cjs',
    'scripts/live_homepage_acceptance.cjs',
    'scripts/live_real_estate_acceptance.cjs',
    'scripts/live_seo_migration_acceptance.cjs',
    'scripts/live_sitewide_acceptance.cjs',
    'scripts/read_release_version.py',
    'scripts/release.ps1',
    'scripts/verify_release_receipt.py',
    'tests/bangkok-rental-data.test.py',
    'tests/content-migration-ledger.test.py',
    'tests/geography-builder.test.py',
    'tests/draft-content-inventory.test.py',
    'tests/geography-resolver.test.php',
    'tests/guides-runtime.test.php',
    'tests/live-sitewide-acceptance.test.cjs',
    'tests/priority-guides-compiler.test.py',
    'tests/queued-expired-content.test.py',
    'tests/real-estate-content.test.py',
    'tests/real-estate-runtime.test.php',
    'tests/run.php',
    'tests/seo-ownership-registry.test.py',
    'tests/seo-runtime-gates.test.py',
    'tests/tawk-state.test.js'
)
$releaseInputs += Get-Content -LiteralPath (Join-Path $repositoryRoot 'package-files.txt')
$releaseInputs = $releaseInputs | Sort-Object -Unique

foreach ($relativePath in $releaseInputs) {
    $absolutePath = Join-Path $repositoryRoot $relativePath
    if (-not (Test-Path -LiteralPath $absolutePath -PathType Leaf)) {
        throw "Release input is missing: $relativePath"
    }

    & $trustedGit -C $repositoryRoot ls-files --error-unmatch -- $relativePath *> $null
    if ($LASTEXITCODE -ne 0) {
        throw "Release input is not tracked by Git: $relativePath"
    }

    $indexHash = (& $trustedGit -C $repositoryRoot rev-parse "HEAD:$relativePath").Trim()
    $worktreeHash = (& $trustedGit -C $repositoryRoot hash-object "--path=$relativePath" -- $absolutePath).Trim()
    if ($indexHash -ne $worktreeHash) {
        throw "Release input differs from the reviewed Git index: $relativePath"
    }
}

$temporaryRoot = Join-Path ([IO.Path]::GetTempPath()) ("thailand-platform-release-" + [Guid]::NewGuid().ToString('N'))
$resolvedTempParent = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$resolvedTemporaryRoot = [IO.Path]::GetFullPath($temporaryRoot)
if (-not $resolvedTemporaryRoot.StartsWith($resolvedTempParent, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Temporary release directory escaped the system temporary directory.'
}

$distributionRoot = [IO.Path]::GetFullPath((Join-Path $repositoryRoot 'plugin-dist'))
if (-not $distributionRoot.StartsWith([IO.Path]::GetFullPath($repositoryRoot) + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Distribution directory escaped the repository.'
}

$expectedRelativePath = "plugin-dist/$version/thailand-platform-$version.zip"
$expectedRelativeReceipt = "plugin-dist/$version/thailand-platform-$version.receipt.json"
$finalVersionDirectory = Join-Path $distributionRoot $version
if (Test-Path -LiteralPath $finalVersionDirectory) {
    throw "Versioned distribution already exists and will not be overwritten: $finalVersionDirectory"
}

$candidatePublishDirectory = Join-Path $distributionRoot ('.candidate-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $resolvedTemporaryRoot | Out-Null

try {
    $archivePath = Join-Path $resolvedTemporaryRoot 'source.tar'
    $frozenSource = Join-Path $resolvedTemporaryRoot 'source'
    New-Item -ItemType Directory -Path $frozenSource | Out-Null
    & $trustedGit -C $repositoryRoot archive --format=tar --output=$archivePath $sourceCommit
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not materialize the reviewed Git tree.'
    }
    & $trustedTar -xf $archivePath -C $frozenSource
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not extract the reviewed Git tree.'
    }

    $builder = Join-Path $frozenSource 'scripts\build_plugin_zip.py'
    $assetBuilder = Join-Path $frozenSource 'scripts\build_homepage_assets.py'
    $bangkokAssetBuilder = Join-Path $frozenSource 'scripts\build_bangkok_rental_assets.py'
    $bangkokRegistryBuilder = Join-Path $frozenSource 'scripts\build_bangkok_rental_registry.py'
    $contentBuilder = Join-Path $frozenSource 'scripts\build_content_registry.py'
    $contentMigrationLedgerBuilder = Join-Path $frozenSource 'scripts\build_content_migration_ledger.py'
    $geographyBuilder = Join-Path $frozenSource 'scripts\build_geography_registry.py'
    $guideAssetBuilder = Join-Path $frozenSource 'scripts\build_guide_assets.py'
    $priorityGuidesBuilder = Join-Path $frozenSource 'scripts\build_priority_guides_registry.py'
    $seoRegistryBuilder = Join-Path $frozenSource 'scripts\build_seo_registry.py'
    $seoRuntimeBuilder = Join-Path $frozenSource 'scripts\build_seo_runtime.py'
    $contentRegistryTest = Join-Path $frozenSource 'tests\real-estate-content.test.py'
    $contentMigrationLedgerTest = Join-Path $frozenSource 'tests\content-migration-ledger.test.py'
    $contentRuntimeTest = Join-Path $frozenSource 'tests\real-estate-runtime.test.php'
    $bangkokDataTest = Join-Path $frozenSource 'tests\bangkok-rental-data.test.py'
    $draftContentInventoryTest = Join-Path $frozenSource 'tests\draft-content-inventory.test.py'
    $geographyBuilderTest = Join-Path $frozenSource 'tests\geography-builder.test.py'
    $priorityGuidesCompilerTest = Join-Path $frozenSource 'tests\priority-guides-compiler.test.py'
    $queuedExpiredContentTest = Join-Path $frozenSource 'tests\queued-expired-content.test.py'
    $guidesRuntimeTest = Join-Path $frozenSource 'tests\guides-runtime.test.php'
    $seoRegistryTest = Join-Path $frozenSource 'tests\seo-ownership-registry.test.py'
    $seoRuntimeTest = Join-Path $frozenSource 'tests\seo-runtime-gates.test.py'
    $sitewideAcceptanceContractTest = Join-Path $frozenSource 'tests\live-sitewide-acceptance.test.cjs'
    $validator = Join-Path $frozenSource 'scripts\verify_release_receipt.py'
    $candidateA = Join-Path $resolvedTemporaryRoot 'candidate-a.zip'
    $candidateB = Join-Path $resolvedTemporaryRoot 'candidate-b.zip'

    $assetCheckOutput = & $pythonExecutable $assetBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated homepage asset verification failed.`n$assetCheckOutput"
    }

    $bangkokAssetCheckOutput = & $pythonExecutable $bangkokAssetBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated Bangkok rental asset verification failed.`n$bangkokAssetCheckOutput"
    }

    $guideAssetCheckOutput = & $pythonExecutable $guideAssetBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated guide asset verification failed.`n$guideAssetCheckOutput"
    }

    $bangkokRegistryCheckOutput = & $pythonExecutable $bangkokRegistryBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated Bangkok rental registry verification failed.`n$bangkokRegistryCheckOutput"
    }

    $geographyCheckOutput = & $pythonExecutable $geographyBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated geography registry verification failed.`n$geographyCheckOutput"
    }

    $contentCheckOutput = & $pythonExecutable $contentBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated content registry verification failed.`n$contentCheckOutput"
    }

    $priorityGuidesCheckOutput = & $pythonExecutable $priorityGuidesBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated priority guides registry verification failed.`n$priorityGuidesCheckOutput"
    }

    $seoRegistryCheckOutput = & $pythonExecutable $seoRegistryBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated SEO ownership registry verification failed.`n$seoRegistryCheckOutput"
    }

    $seoRuntimeCheckOutput = & $pythonExecutable $seoRuntimeBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated SEO runtime verification failed.`n$seoRuntimeCheckOutput"
    }

    $contentMigrationLedgerCheckOutput = & $pythonExecutable $contentMigrationLedgerBuilder
    if ($LASTEXITCODE -ne 0) {
        throw "Content migration ledger verification failed.`n$contentMigrationLedgerCheckOutput"
    }

    $geographyTestOutput = & $pythonExecutable $geographyBuilderTest
    if ($LASTEXITCODE -ne 0) {
        throw "Geography builder tests failed.`n$geographyTestOutput"
    }

    $contentRegistryTestOutput = & $pythonExecutable $contentRegistryTest
    if ($LASTEXITCODE -ne 0) {
        throw "Real-estate content registry tests failed.`n$contentRegistryTestOutput"
    }

    $contentRuntimeTestOutput = & $phpExecutable $contentRuntimeTest
    if ($LASTEXITCODE -ne 0) {
        throw "Real-estate runtime tests failed.`n$contentRuntimeTestOutput"
    }

    $priorityGuidesCompilerTestOutput = & $pythonExecutable $priorityGuidesCompilerTest
    if ($LASTEXITCODE -ne 0) {
        throw "Priority guides compiler tests failed.`n$priorityGuidesCompilerTestOutput"
    }

    $guidesRuntimeTestOutput = & $phpExecutable $guidesRuntimeTest
    if ($LASTEXITCODE -ne 0) {
        throw "Priority guides runtime tests failed.`n$guidesRuntimeTestOutput"
    }

    $bangkokDataTestOutput = & $pythonExecutable $bangkokDataTest
    if ($LASTEXITCODE -ne 0) {
        throw "Bangkok rental data tests failed.`n$bangkokDataTestOutput"
    }

    $draftContentInventoryTestOutput = & $pythonExecutable $draftContentInventoryTest
    if ($LASTEXITCODE -ne 0) {
        throw "Draft-content inventory tests failed.`n$draftContentInventoryTestOutput"
    }

    $contentMigrationLedgerTestOutput = & $pythonExecutable $contentMigrationLedgerTest
    if ($LASTEXITCODE -ne 0) {
        throw "Content migration ledger tests failed.`n$contentMigrationLedgerTestOutput"
    }

    $queuedExpiredContentTestOutput = & $pythonExecutable $queuedExpiredContentTest
    if ($LASTEXITCODE -ne 0) {
        throw "Queued expired-content tests failed.`n$queuedExpiredContentTestOutput"
    }

    $seoTestOutput = & $pythonExecutable $seoRegistryTest
    if ($LASTEXITCODE -ne 0) {
        throw "SEO ownership registry tests failed.`n$seoTestOutput"
    }

    $seoRuntimeTestOutput = & $pythonExecutable $seoRuntimeTest
    if ($LASTEXITCODE -ne 0) {
        throw "SEO runtime gate tests failed.`n$seoRuntimeTestOutput"
    }

    $sitewideAcceptanceContractTestOutput = & $nodeExecutable $sitewideAcceptanceContractTest
    if ($LASTEXITCODE -ne 0 -or ($sitewideAcceptanceContractTestOutput -join "`n").Trim() -ne 'PASS: sitewide acceptance contract') {
        throw "Sitewide acceptance contract tests failed.`n$sitewideAcceptanceContractTestOutput"
    }

    $buildAOutput = & $pythonExecutable $builder --root $frozenSource --out $candidateA --php-bin $phpExecutable --node-bin $nodeExecutable --source-commit $sourceCommit
    if ($LASTEXITCODE -ne 0) {
        throw "First deterministic build failed.`n$buildAOutput"
    }
    $buildBOutput = & $pythonExecutable $builder --root $frozenSource --out $candidateB --php-bin $phpExecutable --node-bin $nodeExecutable --source-commit $sourceCommit --receipt-artifact-path $expectedRelativePath
    if ($LASTEXITCODE -ne 0) {
        throw "Second deterministic build failed.`n$buildBOutput"
    }

    $firstHash = (Get-FileHash -LiteralPath $candidateA -Algorithm SHA256).Hash.ToLowerInvariant()
    $secondHash = (Get-FileHash -LiteralPath $candidateB -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($firstHash -ne $secondHash) {
        throw "Deterministic build mismatch: $firstHash != $secondHash"
    }

    $candidateBReceipt = [IO.Path]::ChangeExtension($candidateB, '.receipt.json')
    & $pythonExecutable $validator --receipt $candidateBReceipt --artifact $candidateB --source-root $frozenSource --source-commit $sourceCommit --version $version --expected-path $expectedRelativePath --python-bin $pythonExecutable --php-bin $phpExecutable --node-bin $nodeExecutable
    if ($LASTEXITCODE -ne 0) {
        throw 'Strict candidate receipt verification failed.'
    }

    $headAfterBuild = (& $trustedGit -C $repositoryRoot rev-parse HEAD).Trim().ToLowerInvariant()
    $dirtyAfterBuild = (& $trustedGit -C $repositoryRoot status --porcelain=v1 --untracked-files=all)
    if ($headAfterBuild -ne $sourceCommit -or $dirtyAfterBuild) {
        throw 'Repository changed while the frozen release was being built.'
    }

    New-Item -ItemType Directory -Path $distributionRoot -Force | Out-Null
    New-Item -ItemType Directory -Path $candidatePublishDirectory | Out-Null
    $publishedCandidateZip = Join-Path $candidatePublishDirectory "thailand-platform-$version.zip"
    $publishedCandidateReceipt = Join-Path $candidatePublishDirectory "thailand-platform-$version.receipt.json"
    [IO.File]::Copy($candidateB, $publishedCandidateZip, $false)
    [IO.File]::Copy($candidateBReceipt, $publishedCandidateReceipt, $false)

    & $pythonExecutable $validator --receipt $publishedCandidateReceipt --artifact $publishedCandidateZip --source-root $frozenSource --source-commit $sourceCommit --version $version --expected-path $expectedRelativePath --python-bin $pythonExecutable --php-bin $phpExecutable --node-bin $nodeExecutable
    if ($LASTEXITCODE -ne 0) {
        throw 'Strict publish-candidate verification failed.'
    }

    [IO.Directory]::Move($candidatePublishDirectory, $finalVersionDirectory)
    $finalZip = Join-Path $finalVersionDirectory "thailand-platform-$version.zip"
    $finalReceipt = Join-Path $finalVersionDirectory "thailand-platform-$version.receipt.json"

    [PSCustomObject]@{
        artifact = $expectedRelativePath
        bytes = (Get-Item -LiteralPath $finalZip).Length
        node = [IO.Path]::GetFileName($nodeExecutable)
        python = [IO.Path]::GetFileName($pythonExecutable)
        php = [IO.Path]::GetFileName($phpExecutable)
        receipt = $expectedRelativeReceipt
        reproducible = $true
        sha256 = (Get-FileHash -LiteralPath $finalZip -Algorithm SHA256).Hash.ToLowerInvariant()
        source_commit = $sourceCommit
        version = $version
    } | ConvertTo-Json
}
finally {
    if (Test-Path -LiteralPath $candidatePublishDirectory) {
        $candidateFullPath = [IO.Path]::GetFullPath($candidatePublishDirectory)
        if (-not $candidateFullPath.StartsWith($distributionRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
            throw 'Candidate cleanup target escaped the distribution directory.'
        }
        Remove-Item -LiteralPath $candidateFullPath -Recurse -Force
    }
    if (Test-Path -LiteralPath $resolvedTemporaryRoot) {
        Remove-Item -LiteralPath $resolvedTemporaryRoot -Recurse -Force
    }
}

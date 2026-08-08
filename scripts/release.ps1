[CmdletBinding()]
param(
    [string]$PhpBinary = 'php',
    [string]$PythonBinary = 'python'
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
$pythonExecutable = $pythonCommand.Source
$phpExecutable = $phpCommand.Source

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
    'data/geography/aliases.csv',
    'data/geography/geometry.json',
    'data/geography/normalization-vectors.json',
    'data/geography/provinces.csv',
    'data/geography/regions.json',
    'data/geography/registry.json',
    'data/geography/registry.schema.json',
    'data/geography/relations.json',
    'data/seo/README.md',
    'data/seo/ownership-registry.json',
    'data/seo/ownership-registry.schema.json',
    'package-files.txt',
    'prototype/app.js',
    'prototype/assets/homepage-hero-thailand-system-v1-1024.webp',
    'prototype/assets/homepage-hero-thailand-system-v1-1713.webp',
    'prototype/assets/homepage-hero-thailand-system-v1-640.webp',
    'prototype/index.html',
    'prototype/styles.css',
    'release.json',
    'scripts/build_homepage_assets.py',
    'scripts/build_geography_registry.py',
    'scripts/build_plugin_zip.py',
    'scripts/read_release_version.py',
    'scripts/release.ps1',
    'scripts/verify_release_receipt.py',
    'tests/geography-builder.test.py',
    'tests/geography-resolver.test.php',
    'tests/run.php',
    'tests/seo-ownership-registry.test.py',
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
    $geographyBuilder = Join-Path $frozenSource 'scripts\build_geography_registry.py'
    $geographyBuilderTest = Join-Path $frozenSource 'tests\geography-builder.test.py'
    $seoRegistryTest = Join-Path $frozenSource 'tests\seo-ownership-registry.test.py'
    $validator = Join-Path $frozenSource 'scripts\verify_release_receipt.py'
    $candidateA = Join-Path $resolvedTemporaryRoot 'candidate-a.zip'
    $candidateB = Join-Path $resolvedTemporaryRoot 'candidate-b.zip'

    $assetCheckOutput = & $pythonExecutable $assetBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated homepage asset verification failed.`n$assetCheckOutput"
    }

    $geographyCheckOutput = & $pythonExecutable $geographyBuilder --check
    if ($LASTEXITCODE -ne 0) {
        throw "Generated geography registry verification failed.`n$geographyCheckOutput"
    }

    $geographyTestOutput = & $pythonExecutable $geographyBuilderTest
    if ($LASTEXITCODE -ne 0) {
        throw "Geography builder tests failed.`n$geographyTestOutput"
    }

    $seoTestOutput = & $pythonExecutable $seoRegistryTest
    if ($LASTEXITCODE -ne 0) {
        throw "SEO ownership registry tests failed.`n$seoTestOutput"
    }

    $buildAOutput = & $pythonExecutable $builder --root $frozenSource --out $candidateA --php-bin $phpExecutable --source-commit $sourceCommit
    if ($LASTEXITCODE -ne 0) {
        throw "First deterministic build failed.`n$buildAOutput"
    }
    $buildBOutput = & $pythonExecutable $builder --root $frozenSource --out $candidateB --php-bin $phpExecutable --source-commit $sourceCommit --receipt-artifact-path $expectedRelativePath
    if ($LASTEXITCODE -ne 0) {
        throw "Second deterministic build failed.`n$buildBOutput"
    }

    $firstHash = (Get-FileHash -LiteralPath $candidateA -Algorithm SHA256).Hash.ToLowerInvariant()
    $secondHash = (Get-FileHash -LiteralPath $candidateB -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($firstHash -ne $secondHash) {
        throw "Deterministic build mismatch: $firstHash != $secondHash"
    }

    $candidateBReceipt = [IO.Path]::ChangeExtension($candidateB, '.receipt.json')
    & $pythonExecutable $validator --receipt $candidateBReceipt --artifact $candidateB --source-root $frozenSource --source-commit $sourceCommit --version $version --expected-path $expectedRelativePath --python-bin $pythonExecutable --php-bin $phpExecutable
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

    & $pythonExecutable $validator --receipt $publishedCandidateReceipt --artifact $publishedCandidateZip --source-root $frozenSource --source-commit $sourceCommit --version $version --expected-path $expectedRelativePath --python-bin $pythonExecutable --php-bin $phpExecutable
    if ($LASTEXITCODE -ne 0) {
        throw 'Strict publish-candidate verification failed.'
    }

    [IO.Directory]::Move($candidatePublishDirectory, $finalVersionDirectory)
    $finalZip = Join-Path $finalVersionDirectory "thailand-platform-$version.zip"
    $finalReceipt = Join-Path $finalVersionDirectory "thailand-platform-$version.receipt.json"

    [PSCustomObject]@{
        artifact = $expectedRelativePath
        bytes = (Get-Item -LiteralPath $finalZip).Length
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

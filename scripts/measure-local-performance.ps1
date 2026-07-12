param(
    [string] $BaseUrl = 'http://localhost:8087',
    [int] $Samples = 5
)

$paths = @('/up', '/login')

foreach ($path in $paths) {
    $measurements = @()

    1..$Samples | ForEach-Object {
        $watch = [System.Diagnostics.Stopwatch]::StartNew()
        $response = Invoke-WebRequest -Uri "$BaseUrl$path" -UseBasicParsing
        $watch.Stop()
        $measurements += $watch.ElapsedMilliseconds

        if ($response.StatusCode -ne 200) {
            throw "Resposta inesperada em ${path}: $($response.StatusCode)"
        }
    }

    $ordered = $measurements | Sort-Object
    [PSCustomObject]@{
        Rota = $path
        Amostras = $Samples
        MinimoMs = $ordered[0]
        MedianaMs = $ordered[[math]::Floor($ordered.Count / 2)]
        MaximoMs = $ordered[-1]
    }
}

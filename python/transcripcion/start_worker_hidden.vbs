Option Explicit

Dim shell, pythonExe, workerPy, workDir, logFile, errorFile, command
Set shell = CreateObject("WScript.Shell")

If WScript.Arguments.Count < 5 Then
    WScript.Quit 2
End If

pythonExe = WScript.Arguments(0)
workerPy = WScript.Arguments(1)
workDir = WScript.Arguments(2)
logFile = WScript.Arguments(3)
errorFile = WScript.Arguments(4)

' Run through cmd only for output redirection. waitOnReturn=False is important:
' Apache/PHP returns immediately and the worker remains independent.
command = "cmd.exe /D /S /C " & Chr(34) & Chr(34) & pythonExe & Chr(34) & _
          " " & Chr(34) & workerPy & Chr(34) & _
          " --daemon --device cpu --compute-type int8" & _
          " 1>>" & Chr(34) & logFile & Chr(34) & _
          " 2>>" & Chr(34) & errorFile & Chr(34) & Chr(34)

shell.CurrentDirectory = workDir
shell.Run command, 0, False
WScript.Quit 0

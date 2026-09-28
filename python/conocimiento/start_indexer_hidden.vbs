Option Explicit

Dim shell, pythonExe, scriptPath, workDir, outLog, errLog, modeArg, command
Set shell = CreateObject("WScript.Shell")

If WScript.Arguments.Count < 6 Then
    WScript.Quit 2
End If

pythonExe = WScript.Arguments(0)
scriptPath = WScript.Arguments(1)
workDir = WScript.Arguments(2)
outLog = WScript.Arguments(3)
errLog = WScript.Arguments(4)
modeArg = WScript.Arguments(5)

command = "cmd.exe /D /S /C " & Chr(34) & Chr(34) & pythonExe & Chr(34) & _
          " " & Chr(34) & scriptPath & Chr(34) & _
          " " & modeArg & _
          " 1>>" & Chr(34) & outLog & Chr(34) & _
          " 2>>" & Chr(34) & errLog & Chr(34) & Chr(34)

shell.CurrentDirectory = workDir
shell.Run command, 0, False
WScript.Quit 0

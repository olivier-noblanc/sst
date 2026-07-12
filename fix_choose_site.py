import sys

path = r'C:\Users\raver\source\repos\sst\pages\choose_site.php'
with open(path, 'rb') as f:
    content = f.read()

old_crlf = b'        $chosenTime = strtotime((string) $siteChosenAt);\r\n        $daysSinceChoice = (time() - $chosenTime) / 86400;'
new_crlf = b'        $chosenTime = strtotime((string) $siteChosenAt);\r\n        if ($chosenTime === false) {\r\n            $chosenTime = 0;\r\n        }\r\n        $daysSinceChoice = (time() - $chosenTime) / 86400;'

old_lf = b'        $chosenTime = strtotime((string) $siteChosenAt);\n        $daysSinceChoice = (time() - $chosenTime) / 86400;'
new_lf = b'        $chosenTime = strtotime((string) $siteChosenAt);\n        if ($chosenTime === false) {\n            $chosenTime = 0;\n        }\n        $daysSinceChoice = (time() - $chosenTime) / 86400;'

if old_crlf in content:
    content = content.replace(old_crlf, new_crlf)
    with open(path, 'wb') as f:
        f.write(content)
    print('OK (CRLF)')
elif old_lf in content:
    content = content.replace(old_lf, new_lf)
    with open(path, 'wb') as f:
        f.write(content)
    print('OK (LF)')
else:
    # Show bytes around the area
    idx = content.find(b'chosenTime = strtotime')
    print('NOT FOUND')
    print(repr(content[idx-30:idx+120]))

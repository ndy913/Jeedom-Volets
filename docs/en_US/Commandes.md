Pour chaque équipement, le plugin va créer des commandes.

image::../images/Volets_screenshot_Widget.jpg[]

=== Gestion Active

Cette commande permet de déterminer quelle gestion est en cours actuellement.

- `Day` : il fait jour, on active toutes les autres gestions. On vérife les autres gestions avant d'exécuter les actions.
- `Night` : il fait nuit, toutes les autres gestions sont désactivées.
- `Present` : il n'y a personne à la maison, on ferme les volets. La gestion de présence interdit toutes autres actions hormis la nuit.
- `Meteo` : si toutes les conditions météo sont vérifiées, on ferme les volets. La gestion météo interdit toutes autres gestions hormis la gestion `Night`.
- `Azimuth` : si le soleil est dans lé fenêtre, on ferme les volets. La gestion par azimuth autorise toutes autres gestions.    

=== La position du volet et son état

Une commande nous permet de mettre à jour manuellement l'état de la position du volet vue par le plugin.
Cette commande nous permet de faire gérer certaines options par scénarios.
L'état est également visible depuis le widget afin de faciliter la compréhension du plugin.

=== Le mode et son état

Ces 2 commandes vont permettre de basculer le plugin en mode "été" ou "hiver".
C'est à vous de déterminer à quel moment il faut gérer ce changement.

image::../images/ModeClose.png[]
L'icône ci-dessus montre le mode "été", le volet est fermé lorsque le soleil est dans la fenêtre.

image::../images/ModeOpen.png[] 
L'icône ci-dessus montre le mode "hiver", le volet est ouvert lorsque le soleil est dans la fenêtre.

=== la position du soleil
Cette commande nous informe si le soleil est dans la fenêtre ou pas.

image::../images/SunInWindows.png[] 
Dans la fenêtre.

image::../images/SunOutWindows.png[]    
Hors fenêtre.

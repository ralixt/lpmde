#!/usr/bin/env python3
import sqlite3

# Connexion à la BD SQLite
conn = sqlite3.connect('var/data_dev.db')
cursor = conn.cursor()

# Vérifier les doublons
print("=== Vérification des doublons d'email ===\n")
cursor.execute('SELECT email, COUNT(*) as count FROM "user" GROUP BY email HAVING count > 1')
doublons = cursor.fetchall()

if doublons:
    print("Doublons trouvés:")
    for email, count in doublons:
        print(f"  {email}: {count} entrées")
    
    # Afficher les IDs des doublons
    print("\n=== Détail des IDs ===")
    for email, count in doublons:
        cursor.execute('SELECT id FROM "user" WHERE email = ? ORDER BY id', (email,))
        ids = [row[0] for row in cursor.fetchall()]
        print(f"  {email}: IDs {ids} (garder ID {ids[0]}, supprimer {ids[1:]})")
    
    # Supprimer les doublons (garder le premier ID pour chaque email)
    print("\n=== Suppression des doublons ===")
    for email, count in doublons:
        cursor.execute('SELECT id FROM "user" WHERE email = ? ORDER BY id DESC LIMIT -1 OFFSET 1', (email,))
        ids_to_delete = [row[0] for row in cursor.fetchall()]
        for user_id in ids_to_delete:
            cursor.execute('DELETE FROM "user" WHERE id = ?', (user_id,))
            print(f"  Suppression: ID {user_id} ({email})")
    
    conn.commit()
    print("\nBase de données nettoyée avec succès!")
else:
    print("Aucun doublon d'email détecté. La BD est propre!")

conn.close()

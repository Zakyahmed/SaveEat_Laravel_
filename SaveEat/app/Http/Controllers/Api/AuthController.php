<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;


class AuthController extends Controller
{
    /**
     * Connexion d'un utilisateur
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $utilisateur = Utilisateur::where('util_email', $request->email)->first();

        if (!$utilisateur || !Hash::check($request->password, $utilisateur->util_mdp)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.']
            ]);
        }

        // Mettre à jour la dernière connexion
        $utilisateur->util_derniere_connexion = now();
        $utilisateur->save();

        // Récupérer le type d'utilisateur
        $userType = $utilisateur->getUserType();

        // Générer un token
        $token = $utilisateur->createToken('auth_token')->plainTextToken;

        return response()->json([
            'utilisateur' => [
                'id' => $utilisateur->util_id,
                'nom' => $utilisateur->util_nom,
                'prenom' => $utilisateur->util_prenom,
                'email' => $utilisateur->util_email,
                'type' => $userType,
                'roles' => $utilisateur->getRoleNames()
            ],
            'token' => $token
        ]);
    }

    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:sea_utilisateur,util_email',
            'password' => 'required|min:8|confirmed',
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'type' => 'required|in:restaurant,association',
            // Ajoutez d'autres validations selon vos besoins
        ]);

        $utilisateur = new Utilisateur();
        $utilisateur->util_email = $request->email;
        $utilisateur->util_mdp = Hash::make($request->password);
        $utilisateur->util_nom = $request->nom;
        $utilisateur->util_prenom = $request->prenom;
        $utilisateur->util_date_inscription = now()->format('Y-m-d');
        $utilisateur->save();

        // Assigner le rôle en fonction du type d'utilisateur
        $utilisateur->assignRole($request->type);

        // Vous voudrez peut-être enregistrer des données spécifiques au type (restaurant/association)
        // selon votre logique métier

        // Générer un token pour le nouvel utilisateur
        $token = $utilisateur->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Inscription réussie',
            'utilisateur' => [
                'id' => $utilisateur->util_id,
                'email' => $utilisateur->util_email,
                'nom' => $utilisateur->util_nom,
                'prenom' => $utilisateur->util_prenom,
                'type' => $request->type,
                'roles' => $utilisateur->getRoleNames()
            ],
            'token' => $token
        ], 201);
    }

    /**
     * Déconnexion d'un utilisateur
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Obtenir le profil de l'utilisateur connecté
     */
    public function profile(Request $request): JsonResponse
    {
        $utilisateur = $request->user();
        $userType = $utilisateur->getUserType();

        $data = [
            'id' => $utilisateur->util_id,
            'email' => $utilisateur->util_email,
            'nom' => $utilisateur->util_nom,
            'prenom' => $utilisateur->util_prenom,
            'telephone' => $utilisateur->util_telephone,
            'date_inscription' => $utilisateur->util_date_inscription,
            'derniere_connexion' => $utilisateur->util_derniere_connexion,
            'type' => $userType,
            'roles' => $utilisateur->getRoleNames()
        ];

        // Ajouter des données spécifiques au type d'utilisateur
        if ($userType === 'restaurant') {
            $data['restaurant'] = $utilisateur->restaurants()->first();
        } elseif ($userType === 'association') {
            $data['association'] = $utilisateur->associations()->first();
        }

        return response()->json(['utilisateur' => $data]);
    }

    /**
     * Mettre à jour le profil de l'utilisateur
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        $request->validate([
            'nom' => 'sometimes|string|max:100',
            'prenom' => 'sometimes|string|max:100',
            'telephone' => 'sometimes|nullable|string|max:20',
            'username' => 'sometimes|nullable|string|max:50|unique:sea_utilisateur,util_username,' . $utilisateur->util_id . ',util_id',
        ]);

        if ($request->has('nom')) {
            $utilisateur->util_nom = $request->nom;
        }
        if ($request->has('prenom')) {
            $utilisateur->util_prenom = $request->prenom;
        }
        if ($request->has('telephone')) {
            $utilisateur->util_telephone = $request->telephone;
        }
        if ($request->has('username')) {
            $utilisateur->util_username = $request->username;
        }

        $utilisateur->save();

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'utilisateur' => $utilisateur
        ]);
    }

    /**
     * Changer le mot de passe de l'utilisateur
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $utilisateur = $request->user();

        if (!Hash::check($request->current_password, $utilisateur->util_mdp)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect'
            ], 422);
        }

        $utilisateur->util_mdp = Hash::make($request->new_password);
        $utilisateur->save();

        return response()->json([
            'message' => 'Mot de passe changé avec succès'
        ]);
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Dans une application réelle, vous enverriez un email avec un lien de réinitialisation
        // Pour cet exemple, nous renverrons simplement un message

        return response()->json([
            'message' => 'Si un compte existe avec cet email, un lien de réinitialisation de mot de passe a été envoyé.'
        ]);
    }
}